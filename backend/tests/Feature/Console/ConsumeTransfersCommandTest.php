<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Services\TransferMessageConsumer;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Junges\Kafka\Contracts\MessageConsumer;
use Junges\Kafka\Facades\Kafka;
use Junges\Kafka\Message\ConsumedMessage;
use Mockery;
use Tests\TestCase;

final class ConsumeTransfersCommandTest extends TestCase
{
    public function test_dry_run_does_not_commit_and_passes_flag_to_consumer(): void
    {
        Kafka::fake();

        $message = $this->buildMessage([
            'transfer_id' => 42,
            'payer_id' => 1,
            'payee_id' => 2,
            'amount' => 1000,
        ]);

        Kafka::shouldReceiveMessages([
            new ConsumedMessage(
                topicName: 'wallet.transfer.completed',
                partition: 0,
                headers: [],
                body: $message,
                key: '42',
                offset: 1,
                timestamp: time(),
            ),
        ]);

        $consumer = $this->instance(TransferMessageConsumer::class, $this->mock(TransferMessageConsumer::class));
        $consumer->shouldReceive('consume')
            ->once()
            ->with($message, true);

        $messageConsumer = Mockery::mock(MessageConsumer::class);
        $messageConsumer->shouldReceive('commit')
            ->never();

        $this->artisan('kafka:consume-transfers', ['--dry-run' => true])
            ->assertSuccessful()
            ->expectsOutputToContain('[DRY-RUN] Enabled')
            ->expectsOutputToContain('[DRY-RUN] Kafka consumer will not commit offsets')
            ->expectsOutputToContain('[DRY-RUN] Kafka offset commit skipped');
    }

    public function test_normal_mode_commits_offset_after_processing(): void
    {
        Kafka::fake();

        $message = $this->buildMessage([
            'transfer_id' => 43,
            'payer_id' => 1,
            'payee_id' => 2,
            'amount' => 1000,
        ]);

        Kafka::shouldReceiveMessages([
            new ConsumedMessage(
                topicName: 'wallet.transfer.completed',
                partition: 0,
                headers: [],
                body: $message,
                key: '43',
                offset: 1,
                timestamp: time(),
            ),
        ]);

        $consumer = $this->instance(TransferMessageConsumer::class, $this->mock(TransferMessageConsumer::class));
        $consumer->shouldReceive('consume')
            ->once()
            ->with($message, false);

        $this->artisan('kafka:consume-transfers')
            ->assertSuccessful()
            ->doesntExpectOutputToContain('[DRY-RUN] Enabled');
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
                'event' => 'transfer.completed',
                'occurred_at' => now()->toIso8601String(),
            ],
            'payload' => $payload,
        ];
    }
}
