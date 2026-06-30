<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Services\DryRun\DryRunContext;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Junges\Kafka\Facades\Kafka;
use Junges\Kafka\Message\ConsumedMessage;
use Tests\TestCase;

final class ConsumeRetryTransfersCommandTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_dry_run_enables_context_and_records_retry_wait_skipped(): void
    {
        Kafka::fake();

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
                'transfer_id' => 'txn_retry_dry_1',
                'payer_id' => 1,
                'payee_id' => 2,
                'amount_cents' => 1000,
            ],
        ];

        Kafka::shouldReceiveMessages([
            new ConsumedMessage(
                topicName: 'wallet.transfer.retry',
                partition: 0,
                headers: [],
                body: $message,
                key: 'txn_retry_dry_1',
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
                'transfer_id' => 'txn_retry_live_1',
                'payer_id' => 1,
                'payee_id' => 2,
                'amount_cents' => 1000,
            ],
        ];

        Kafka::shouldReceiveMessages([
            new ConsumedMessage(
                topicName: 'wallet.transfer.retry',
                partition: 0,
                headers: [],
                body: $message,
                key: 'txn_retry_live_1',
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
