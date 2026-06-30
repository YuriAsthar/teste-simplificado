<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Contracts\TransferPublisherInterface;
use App\Services\DryRun\DryRunContext;
use App\Services\TransferMessageConsumer;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Junges\Kafka\Contracts\ConsumerMessage;
use Junges\Kafka\Facades\Kafka;
use Junges\Kafka\Message\ConsumedMessage;
use Tests\TestCase;

final class ConsumeTransfersCommandTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_dry_run_enables_context_and_forces_manual_commit(): void
    {
        Kafka::fake();

        $message = $this->buildMessage([
            'transfer_id' => 'txn_dry_1',
            'payer_id' => 1,
            'payee_id' => 2,
            'amount_cents' => 1000,
        ]);

        Kafka::shouldReceiveMessages([
            new ConsumedMessage(
                topicName: 'wallet.transfer.completed',
                partition: 0,
                headers: [],
                body: $message,
                key: 'txn_dry_1',
                offset: 1,
                timestamp: time(),
            ),
        ]);

        $this->artisan('kafka:consume-transfers', ['--dry-run' => true])
            ->assertSuccessful()
            ->expectsOutputToContain('[DRY-RUN] Enabled')
            ->expectsOutputToContain('rabbitmq.dispatch');

        $this->assertTrue(app(DryRunContext::class)->isEnabled());
    }

    public function test_normal_mode_does_not_output_dry_run_lines(): void
    {
        Kafka::fake();

        $message = $this->buildMessage([
            'transfer_id' => 'txn_live_1',
            'payer_id' => 1,
            'payee_id' => 2,
            'amount_cents' => 1000,
        ]);

        Kafka::shouldReceiveMessages([
            new ConsumedMessage(
                topicName: 'wallet.transfer.completed',
                partition: 0,
                headers: [],
                body: $message,
                key: 'txn_live_1',
                offset: 1,
                timestamp: time(),
            ),
        ]);

        $this->artisan('kafka:consume-transfers')
            ->assertSuccessful()
            ->doesntExpectOutputToContain('[DRY-RUN] Enabled')
            ->doesntExpectOutputToContain('rabbitmq.dispatch');

        $this->assertFalse(app(DryRunContext::class)->isEnabled());
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    private function buildMessage(array $payload): array
    {
        return [
            'meta' => [
                'version' => '1.0',
                'event' => 'transfer.authorized',
                'occurred_at' => now()->toIso8601String(),
            ],
            'payload' => $payload,
        ];
    }
}
