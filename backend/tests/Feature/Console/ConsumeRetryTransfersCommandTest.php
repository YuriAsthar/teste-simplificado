<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Enums\TransferStatus;
use App\Models\Transfer;
use App\Models\User;
use App\Services\DryRun\DryRunContext;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Junges\Kafka\Facades\Kafka;
use Junges\Kafka\Message\ConsumedMessage;
use Tests\TestCase;

final class ConsumeRetryTransfersCommandTest extends TestCase
{
    public function test_dry_run_enables_context_and_records_retry_wait_skipped(): void
    {
        Kafka::fake();

        $transfer = Transfer::factory()->create([
            'payer_id' => User::factory()->create()->id,
            'payee_id' => User::factory()->create()->id,
            'amount' => 1000,
            'status' => TransferStatus::Completed,
        ]);

        $message = [
            'meta' => [
                'version' => '1.0',
                'event' => 'transfer.retry',
                'occurred_at' => now()->toIso8601String(),
                'retry' => [
                    'attempt' => 1,
                    'scheduled_at' => now()->addMinute()->toIso8601String(),
                ],
            ],
            'payload' => [
                'transfer_id' => $transfer->id,
                'payer_id' => $transfer->payer_id,
                'payee_id' => $transfer->payee_id,
                'amount' => $transfer->amount,
            ],
        ];

        Kafka::shouldReceiveMessages([
            new ConsumedMessage(
                topicName: 'wallet.transfer.retry',
                partition: 0,
                headers: [],
                body: $message,
                key: (string) $transfer->id,
                offset: 1,
                timestamp: time(),
            ),
        ]);

        $this->artisan('kafka:consume-retry-transfers', ['--dry-run' => true])
            ->assertSuccessful()
            ->expectsOutputToContain('[DRY-RUN] Enabled')
            ->expectsOutputToContain('retry.wait_skipped');

        $this->assertTrue(app(DryRunContext::class)->isEnabled());
    }

    public function test_normal_mode_does_not_output_dry_run_lines(): void
    {
        Kafka::fake();

        $transfer = Transfer::factory()->create([
            'payer_id' => User::factory()->create()->id,
            'payee_id' => User::factory()->create()->id,
            'amount' => 1000,
            'status' => TransferStatus::Completed,
        ]);

        $message = [
            'meta' => [
                'version' => '1.0',
                'event' => 'transfer.retry',
                'occurred_at' => now()->toIso8601String(),
                'retry' => [
                    'attempt' => 1,
                    'scheduled_at' => now()->subMinute()->toIso8601String(),
                ],
            ],
            'payload' => [
                'transfer_id' => $transfer->id,
                'payer_id' => $transfer->payer_id,
                'payee_id' => $transfer->payee_id,
                'amount' => $transfer->amount,
            ],
        ];

        Kafka::shouldReceiveMessages([
            new ConsumedMessage(
                topicName: 'wallet.transfer.retry',
                partition: 0,
                headers: [],
                body: $message,
                key: (string) $transfer->id,
                offset: 1,
                timestamp: time(),
            ),
        ]);

        $this->artisan('kafka:consume-retry-transfers')
            ->assertSuccessful()
            ->doesntExpectOutputToContain('[DRY-RUN] Enabled')
            ->doesntExpectOutputToContain('retry.wait_skipped');

        $this->assertFalse(app(DryRunContext::class)->isEnabled());
    }
}
