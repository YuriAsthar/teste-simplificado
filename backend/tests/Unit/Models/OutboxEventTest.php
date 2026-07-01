<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Enums\OutboxStatus;
use App\Models\OutboxEvent;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class OutboxEventTest extends TestCase
{
    public function test_pending_scope_returns_only_eligible_events(): void
    {
        OutboxEvent::factory()->create([
            'status' => OutboxStatus::Pending,
            'attempts' => 0,
            'last_error_at' => null,
        ]);

        OutboxEvent::factory()->create([
            'status' => OutboxStatus::Failed,
            'attempts' => 1,
            'last_error_at' => now()->subSeconds(600),
        ]);

        OutboxEvent::factory()->create([
            'status' => OutboxStatus::Failed,
            'attempts' => 3,
            'last_error_at' => now()->subSeconds(600),
        ]);

        OutboxEvent::factory()->create([
            'status' => OutboxStatus::Published,
            'attempts' => 0,
            'last_error_at' => null,
        ]);

        OutboxEvent::factory()->create([
            'status' => OutboxStatus::Failed,
            'attempts' => 1,
            'last_error_at' => now()->subSeconds(60),
        ]);

        config(['outbox.max_attempts' => 3]);
        config(['outbox.retry_interval_seconds' => 300]);

        $pending = OutboxEvent::pending()->pluck('id');

        $this->assertCount(2, $pending);
    }

    public function test_mark_published_sets_status_to_published(): void
    {
        $event = OutboxEvent::factory()->create(['status' => OutboxStatus::Pending]);

        $event->markPublished();

        $this->assertSame(OutboxStatus::Published, $event->fresh()->status);
    }

    public function test_mark_failed_sets_status_to_failed_and_increments_attempts(): void
    {
        $event = OutboxEvent::factory()->create([
            'status' => OutboxStatus::Pending,
            'attempts' => 0,
        ]);

        $event->markFailed();

        $fresh = $event->fresh();
        $this->assertSame(OutboxStatus::Failed, $fresh->status);
        $this->assertSame(1, $fresh->attempts);
        $this->assertNotNull($fresh->last_error_at);
    }

    public function test_unique_index_enforces_one_event_per_aggregate_and_type(): void
    {
        OutboxEvent::factory()->create([
            'aggregate_type' => 'transfer',
            'aggregate_id' => 1,
            'event_type' => 'transfer.completed',
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        OutboxEvent::factory()->create([
            'aggregate_type' => 'transfer',
            'aggregate_id' => 1,
            'event_type' => 'transfer.completed',
        ]);
    }

    public function test_payload_is_cast_to_array(): void
    {
        $event = OutboxEvent::factory()->create([
            'payload' => ['foo' => 'bar'],
        ]);

        $this->assertSame(['foo' => 'bar'], $event->fresh()?->payload);
    }

    public function test_status_is_cast_to_enum(): void
    {
        $event = OutboxEvent::factory()->create(['status' => OutboxStatus::Pending]);

        $this->assertSame(OutboxStatus::Pending, $event->fresh()?->status);
    }

    public function test_can_retry_failed_event_after_interval(): void
    {
        $event = OutboxEvent::factory()->create([
            'status' => OutboxStatus::Failed,
            'attempts' => 1,
            'last_error_at' => now()->subSeconds(301),
        ]);

        config(['outbox.max_attempts' => 3]);
        config(['outbox.retry_interval_seconds' => 300]);

        $this->assertTrue(OutboxEvent::pending()->where('id', $event->id)->exists());
    }

    public function test_failed_event_with_max_attempts_is_not_pending(): void
    {
        OutboxEvent::factory()->create([
            'status' => OutboxStatus::Failed,
            'attempts' => 3,
            'last_error_at' => now()->subSeconds(600),
        ]);

        config(['outbox.max_attempts' => 3]);
        config(['outbox.retry_interval_seconds' => 300]);

        $this->assertFalse(OutboxEvent::pending()->exists());
    }
}
