<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Contracts\TransferPublisherInterface;
use App\Enums\OutboxStatus;
use App\Models\OutboxEvent;
use App\Services\TransferMessageBuilder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Junges\Kafka\Facades\Kafka;
use Tests\TestCase;

final class PublishOutboxEventsCommandTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Kafka::fake();
    }

    public function test_publishes_pending_events_and_marks_published(): void
    {
        $event = OutboxEvent::factory()->create([
            'aggregate_type' => 'transfer',
            'aggregate_id' => 1,
            'event_type' => 'transfer.completed',
            'payload' => [
                'transfer_id' => 1,
                'payer_id' => 2,
                'payee_id' => 3,
                'amount' => 1000,
            ],
            'status' => OutboxStatus::Pending,
        ]);

        $this->artisan('outbox:publish')
            ->assertSuccessful()
            ->expectsOutputToContain('Processed 1 outbox events.');

        Kafka::assertPublishedTimes(1);

        $this->assertDatabaseHas('outbox_events', [
            'id' => $event->id,
            'status' => OutboxStatus::Published->value,
        ]);
    }

    public function test_skips_already_published_events(): void
    {
        OutboxEvent::factory()->create([
            'status' => OutboxStatus::Published,
        ]);

        $this->artisan('outbox:publish')
            ->assertSuccessful()
            ->expectsOutputToContain('Processed 0 outbox events.');

        Kafka::assertNothingPublished();
    }

    public function test_marks_failed_event_and_logs_error(): void
    {
        $event = OutboxEvent::factory()->create([
            'status' => OutboxStatus::Pending,
            'payload' => [
                'transfer_id' => 1,
                'payer_id' => 2,
                'payee_id' => 3,
                'amount' => 1000,
            ],
        ]);

        $this->instance(TransferPublisherInterface::class, new class() implements TransferPublisherInterface {
            public function publish(string $topic, array $payload, ?string $key = null): void
            {
                throw new \RuntimeException('Kafka unavailable');
            }
        });

        $this->artisan('outbox:publish')
            ->assertSuccessful()
            ->expectsOutputToContain("Failed to publish outbox event {$event->id}");

        $this->assertDatabaseHas('outbox_events', [
            'id' => $event->id,
            'status' => OutboxStatus::Failed->value,
            'attempts' => 1,
        ]);
    }

    public function test_respects_batch_option(): void
    {
        OutboxEvent::factory()->count(5)->create([
            'status' => OutboxStatus::Pending,
            'payload' => ['transfer_id' => 1],
        ]);

        $this->artisan('outbox:publish', ['--batch' => 2])
            ->assertSuccessful()
            ->expectsOutputToContain('Processed 2 outbox events.');

        Kafka::assertPublishedTimes(2);
    }

    public function test_failed_event_is_retried_after_retry_interval(): void
    {
        $event = OutboxEvent::factory()->create([
            'status' => OutboxStatus::Failed,
            'attempts' => 1,
            'last_error_at' => now()->subSeconds(301),
            'payload' => [
                'transfer_id' => 1,
                'payer_id' => 2,
                'payee_id' => 3,
                'amount' => 1000,
            ],
        ]);

        config(['outbox.max_attempts' => 3]);
        config(['outbox.retry_interval_seconds' => 300]);

        $this->artisan('outbox:publish')
            ->assertSuccessful()
            ->expectsOutputToContain('Processed 1 outbox events.');

        Kafka::assertPublishedTimes(1);

        $this->assertDatabaseHas('outbox_events', [
            'id' => $event->id,
            'status' => OutboxStatus::Published->value,
        ]);
    }

    public function test_message_builder_receives_real_transfer_payload(): void
    {
        $event = OutboxEvent::factory()->create([
            'aggregate_type' => 'transfer',
            'aggregate_id' => 99,
            'event_type' => 'transfer.completed',
            'payload' => [
                'transfer_id' => 99,
                'payer_id' => 2,
                'payee_id' => 3,
                'amount' => 1000,
                'currency' => 'BRA',
                'occurred_at' => now()->toIso8601String(),
            ],
            'status' => OutboxStatus::Pending,
        ]);

        $this->artisan('outbox:publish')->assertSuccessful();

        Kafka::assertPublishedTimes(1);

        $this->assertDatabaseHas('outbox_events', [
            'id' => $event->id,
            'status' => OutboxStatus::Published->value,
        ]);
    }
}
