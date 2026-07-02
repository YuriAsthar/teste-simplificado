<?php

declare(strict_types=1);

namespace Tests\Feature\Outbox;

use App\Enums\TransferStatus;
use App\Jobs\SendNotificationJob;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Junges\Kafka\Facades\Kafka;
use Junges\Kafka\Message\ConsumedMessage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class OutboxIntegrationTest extends TestCase
{
    public function test_full_transfer_to_notification_flow(): void
    {
        Http::fake([
            'https://util.devi.tools/api/v2/authorize' => Http::response([
                'data' => ['authorization' => true],
            ], 200),
        ]);
        Kafka::fake();
        Queue::fake();

        $payer = User::factory()->create();
        $payee = User::factory()->create();
        $payer->wallet->forceFill(['balance' => 10000])->save();

        Sanctum::actingAs($payer);

        $response = $this->postJson('/api/v1/transfer', [
            'payer' => $payer->id,
            'payee' => $payee->id,
            'amount' => 2500,
        ], [
            'Idempotency-Key' => 'outbox-flow',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.status', TransferStatus::Completed->value);

        $transferId = (int) $response->json('data.id');

        $this->assertDatabaseHas('outbox_events', [
            'aggregate_type' => 'transfer',
            'aggregate_id' => $transferId,
            'event_type' => 'transfer.completed',
        ]);

        Queue::assertNothingPushed();

        $this->artisan('outbox:publish')
            ->assertSuccessful()
            ->expectsOutputToContain('Processed 1 outbox events.');

        Kafka::assertPublishedTimes(1);

        $message = [
            'meta' => [
                'version' => '1.0',
                'event' => 'transfer.completed',
                'occurred_at' => now()->toIso8601String(),
            ],
            'payload' => [
                'transfer_id' => $transferId,
                'payer_id' => $payer->id,
                'payee_id' => $payee->id,
                'amount' => 2500,
            ],
        ];

        Kafka::shouldReceiveMessages([
            new ConsumedMessage(
                topicName: 'wallet.transfer.completed',
                partition: 0,
                headers: [],
                body: $message,
                key: (string) $transferId,
                offset: 1,
                timestamp: time(),
            ),
        ]);

        Cache::store(config('kafka.cache_driver'))
            ->forget('kafka:transfer:' . $transferId);

        $this->artisan('kafka:consume-transfers')
            ->assertSuccessful();

        Queue::assertPushed(SendNotificationJob::class, function (SendNotificationJob $job) use ($transferId): bool {
            return $job->transferId === $transferId;
        });
    }
}
