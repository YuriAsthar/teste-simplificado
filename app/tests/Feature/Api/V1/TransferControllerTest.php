<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Contracts\TransferPublisherInterface;
use App\Jobs\SendNotificationJob;
use App\Models\User;
use App\Services\TransferService;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

final class TransferControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->instance(TransferPublisherInterface::class, $this->mock(TransferPublisherInterface::class));
        $this->instance(Dispatcher::class, $this->mock(Dispatcher::class));

        /** @var Repository $cache */
        $cache = $this->mock(Repository::class);
        $cache->shouldReceive('remember')
            ->andReturnUsing(static fn (string $key, int $ttl, callable $callback): mixed => $callback());
        $this->instance(Repository::class, $cache);
    }

    public function test_it_authorizes_transfer_and_dispatches_notification(): void
    {
        $payer = User::factory()->create();
        $payee = User::factory()->create();

        $publisher = $this->app->make(TransferPublisherInterface::class);
        $publisher->shouldReceive('publish')
            ->withArgs(static fn (string $topic, array $envelope, ?string $key): bool => $topic === 'wallet.transfer.completed'
                && is_string($key)
                && str_starts_with($key, 'txn_')
                && isset($envelope['meta'], $envelope['payload'])
                && $envelope['meta']['event'] === 'transfer.authorized'
                && $envelope['meta']['version'] === '1.0'
                && is_string($envelope['meta']['occurred_at'])
                && str_starts_with($envelope['payload']['transfer_id'], 'txn_')
                && $envelope['payload']['payer_id'] === $payer->id
                && $envelope['payload']['payee_id'] === $payee->id
                && $envelope['payload']['amount_cents'] === 1000);

        $dispatcher = $this->app->make(Dispatcher::class);
        $dispatcher->shouldReceive('dispatch')
            ->withArgs(static fn (SendNotificationJob $job): bool =>
                $job->payerId === $payer->id
                && $job->payeeId === $payee->id
                && $job->amountCents === 1000
                && str_starts_with($job->transferId, 'txn_'))
            ->andReturnSelf();
        $dispatcher->shouldReceive('onConnection')->with('rabbitmq')->andReturnSelf();

        $cache = $this->app->make(Repository::class);
        $cache->shouldReceive('forget')->twice();

        $response = $this->postJson('/api/v1/transfers', [
            'payer_id' => $payer->id,
            'payee_id' => $payee->id,
            'amount_cents' => 1000,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.status', 'authorized')
            ->assertJsonStructure([
                'data' => [
                    'transfer_id',
                    'status',
                ],
            ]);
    }

    public function test_it_returns_validation_error_when_payer_does_not_exist(): void
    {
        $payee = User::factory()->create();

        $response = $this->postJson('/api/v1/transfers', [
            'payer_id' => 99999,
            'payee_id' => $payee->id,
            'amount_cents' => 1000,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['payer_id']);
    }

    public function test_it_returns_validation_error_when_amount_is_invalid(): void
    {
        $payer = User::factory()->create();
        $payee = User::factory()->create();

        $response = $this->postJson('/api/v1/transfers', [
            'payer_id' => $payer->id,
            'payee_id' => $payee->id,
            'amount_cents' => 0,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['amount_cents']);
    }

    public function test_it_returns_validation_error_for_missing_fields(): void
    {
        $response = $this->postJson('/api/v1/transfers', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['payer_id', 'payee_id', 'amount_cents']);
    }
}
