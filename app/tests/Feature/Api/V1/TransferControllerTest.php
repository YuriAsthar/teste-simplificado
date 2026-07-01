<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Enums\CurrencyType;
use App\Enums\FailureReason;
use App\Enums\IdempotencyKeyStatus;
use App\Enums\TransferStatus;
use App\Enums\UserType;
use App\Jobs\SendNotificationJob;
use App\Models\IdempotencyKey;
use App\Models\Transfer;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class TransferControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_transfer_requires_authentication(): void
    {
        $payer = User::factory()->create();
        $payee = User::factory()->create();

        $response = $this->postJson('/api/v1/transfer', [
            'payer' => $payer->id,
            'payee' => $payee->id,
            'amount' => 2500,
        ], [
            'Idempotency-Key' => 'auth-required',
        ]);

        $response->assertUnauthorized();
    }

    public function test_transfer_forbidden_when_authenticated_user_is_not_payer(): void
    {
        $payer = User::factory()->create();
        $payee = User::factory()->create();
        $other = User::factory()->create();

        Sanctum::actingAs($other);

        $response = $this->postJson('/api/v1/transfer', [
            'payer' => $payer->id,
            'payee' => $payee->id,
            'amount' => 2500,
        ], [
            'Idempotency-Key' => 'forbidden',
        ]);

        $response->assertForbidden();
    }

    public function test_it_executes_transfer_between_users(): void
    {
        Http::fake([
            'https://util.devi.tools/api/v2/authorize' => Http::response([
                'data' => ['authorization' => true],
            ], 200),
        ]);
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
            'Idempotency-Key' => 'transfer-1',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.status', TransferStatus::Completed->value)
            ->assertJsonPath('data.failure_reason', null);

        $this->assertSame(7500, (int) $payer->fresh()?->wallet->getRawOriginal('balance'));
        $this->assertSame(2500, (int) $payee->fresh()?->wallet->getRawOriginal('balance'));

        Queue::assertPushed(SendNotificationJob::class);
    }

    public function test_it_returns_failed_transfer_when_payer_has_insufficient_funds(): void
    {
        Http::fake([
            'https://util.devi.tools/api/v2/authorize' => Http::response([
                'data' => ['authorization' => true],
            ], 200),
        ]);
        Queue::fake();

        $payer = User::factory()->create();
        $payee = User::factory()->create();

        $payer->wallet->forceFill(['balance' => 100])->save();

        Sanctum::actingAs($payer);

        $response = $this->postJson('/api/v1/transfer', [
            'payer' => $payer->id,
            'payee' => $payee->id,
            'amount' => 500,
        ], [
            'Idempotency-Key' => 'insufficient-funds',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('data.status', TransferStatus::Failed->value)
            ->assertJsonPath('data.failure_reason', FailureReason::InsufficientFunds->value);
    }

    public function test_it_returns_failed_transfer_when_payer_is_merchant(): void
    {
        Http::fake([
            'https://util.devi.tools/api/v2/authorize' => Http::response([
                'data' => ['authorization' => true],
            ], 200),
        ]);
        Queue::fake();

        $merchant = User::factory()->create(['type' => UserType::Merchant->value]);
        $payee = User::factory()->create();

        Sanctum::actingAs($merchant);

        $response = $this->postJson('/api/v1/transfer', [
            'payer' => $merchant->id,
            'payee' => $payee->id,
            'amount' => 1000,
        ], [
            'Idempotency-Key' => 'merchant-payer',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('data.failure_reason', FailureReason::PayerIsMerchant->value);
    }

    public function test_it_returns_422_when_authorizer_rejects_and_allows_retry(): void
    {
        Http::fake([
            'https://util.devi.tools/api/v2/authorize' => Http::sequence()
                ->push([
                    'data' => ['authorization' => false],
                ], 200)
                ->push([
                    'data' => ['authorization' => true],
                ], 200),
        ]);
        Queue::fake();

        $payer = User::factory()->create();
        $payee = User::factory()->create();

        $payer->wallet->forceFill(['balance' => 10000])->save();

        Sanctum::actingAs($payer);

        $payload = [
            'payer' => $payer->id,
            'payee' => $payee->id,
            'amount' => 1000,
        ];

        $response = $this->postJson('/api/v1/transfer', $payload, [
            'Idempotency-Key' => 'authorizer-rejects',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('code', 'authorizer_rejected')
            ->assertJsonPath('message', FailureReason::AuthorizerRejected->description());

        $this->assertDatabaseCount('transfers', 0);
        $this->assertDatabaseMissing('idempotency_keys', [
            'key' => 'authorizer-rejects',
        ]);

        $retry = $this->postJson('/api/v1/transfer', $payload, [
            'Idempotency-Key' => 'authorizer-rejects',
        ]);

        $retry->assertStatus(201)
            ->assertJsonPath('data.status', TransferStatus::Completed->value);
    }

    public function test_it_returns_failed_transfer_when_payee_wallet_is_soft_deleted(): void
    {
        Http::fake([
            'https://util.devi.tools/api/v2/authorize' => Http::response([
                'data' => ['authorization' => true],
            ], 200),
        ]);
        Queue::fake();

        $payer = User::factory()->create();
        $payee = User::factory()->create();

        $payer->wallet->forceFill(['balance' => 10000])->save();
        $payee->wallet->delete();

        Sanctum::actingAs($payer);

        $response = $this->postJson('/api/v1/transfer', [
            'payer' => $payer->id,
            'payee' => $payee->id,
            'amount' => 1000,
        ], [
            'Idempotency-Key' => 'soft-deleted-payee',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('data.failure_reason', FailureReason::WalletInactive->value);
    }

    public function test_it_returns_failed_transfer_when_currencies_mismatch(): void
    {
        Http::fake([
            'https://util.devi.tools/api/v2/authorize' => Http::response([
                'data' => ['authorization' => true],
            ], 200),
        ]);
        Queue::fake();

        $payer = User::factory()->create();
        $payee = User::factory()->create();

        $payer->wallet->forceFill(['balance' => 10000])->save();
        $payee->wallet->update(['currency' => CurrencyType::USD->value]);

        Sanctum::actingAs($payer);

        $response = $this->postJson('/api/v1/transfer', [
            'payer' => $payer->id,
            'payee' => $payee->id,
            'amount' => 1000,
        ], [
            'Idempotency-Key' => 'currency-mismatch',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('data.failure_reason', FailureReason::CurrencyMismatch->value);
    }

    public function test_duplicate_idempotency_key_returns_existing_transfer(): void
    {
        Http::fake([
            'https://util.devi.tools/api/v2/authorize' => Http::response([
                'data' => ['authorization' => true],
            ], 200),
        ]);
        Queue::fake();

        $payer = User::factory()->create();
        $payee = User::factory()->create();

        $payer->wallet->forceFill(['balance' => 10000])->save();

        Sanctum::actingAs($payer);

        $payload = [
            'payer' => $payer->id,
            'payee' => $payee->id,
            'amount' => 1000,
        ];

        $first = $this->postJson('/api/v1/transfer', $payload, [
            'Idempotency-Key' => 'duplicate-key',
        ]);

        $second = $this->postJson('/api/v1/transfer', $payload, [
            'Idempotency-Key' => 'duplicate-key',
        ]);

        $first->assertStatus(201);
        $second->assertStatus(201);
        $this->assertSame(
            $first->json('data.id'),
            $second->json('data.id'),
        );
    }

    public function test_it_returns_409_when_idempotency_key_is_reused_with_different_payload(): void
    {
        Http::fake([
            'https://util.devi.tools/api/v2/authorize' => Http::response([
                'data' => ['authorization' => true],
            ], 200),
        ]);
        Queue::fake();

        $payer = User::factory()->create();
        $payee = User::factory()->create();

        $payer->wallet->forceFill(['balance' => 10000])->save();

        Sanctum::actingAs($payer);

        $this->postJson('/api/v1/transfer', [
            'payer' => $payer->id,
            'payee' => $payee->id,
            'amount' => 1000,
        ], [
            'Idempotency-Key' => 'mismatch-key',
        ])->assertStatus(201);

        $response = $this->postJson('/api/v1/transfer', [
            'payer' => $payer->id,
            'payee' => $payee->id,
            'amount' => 2000,
        ], [
            'Idempotency-Key' => 'mismatch-key',
        ]);

        $response->assertStatus(409)
            ->assertJsonPath('code', 'idempotency_key_reuse_with_different_payload');
    }

    public function test_it_returns_409_when_replay_hits_processing_state(): void
    {
        Http::fake([
            'https://util.devi.tools/api/v2/authorize' => Http::response([
                'data' => ['authorization' => true],
            ], 200),
        ]);
        Queue::fake();

        $payer = User::factory()->create();
        $payee = User::factory()->create();

        Sanctum::actingAs($payer);

        IdempotencyKey::factory()->create([
            'key' => 'processing-key',
            'status' => IdempotencyKeyStatus::Processing,
            'fingerprint' => hash('sha256', implode(':', [$payer->id, $payee->id, 1000])),
        ]);

        $response = $this->postJson('/api/v1/transfer', [
            'payer' => $payer->id,
            'payee' => $payee->id,
            'amount' => 1000,
        ], [
            'Idempotency-Key' => 'processing-key',
        ]);

        $response->assertStatus(409)
            ->assertJsonPath('code', 'transfer_in_progress');
    }

    public function test_missing_idempotency_key_returns_422(): void
    {
        $payer = User::factory()->create();
        $payee = User::factory()->create();

        Sanctum::actingAs($payer);

        $response = $this->postJson('/api/v1/transfer', [
            'payer' => $payer->id,
            'payee' => $payee->id,
            'amount' => 1000,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['idempotency_key']);
    }

    public function test_it_returns_validation_error_when_payer_is_missing(): void
    {
        $payer = User::factory()->create();

        Sanctum::actingAs($payer);

        $response = $this->postJson('/api/v1/transfer', [
            'payee' => $payer->id,
            'amount' => 1000,
        ], [
            'Idempotency-Key' => 'missing-payer',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['payer']);
    }

    public function test_it_returns_validation_error_when_amount_is_invalid(): void
    {
        $payer = User::factory()->create();
        $payee = User::factory()->create();

        Sanctum::actingAs($payer);

        $response = $this->postJson('/api/v1/transfer', [
            'payer' => $payer->id,
            'payee' => $payee->id,
            'amount' => 0,
        ], [
            'Idempotency-Key' => 'invalid-amount',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['amount']);
    }

    public function test_it_returns_validation_error_when_payer_and_payee_are_same(): void
    {
        $payer = User::factory()->create();

        Sanctum::actingAs($payer);

        $response = $this->postJson('/api/v1/transfer', [
            'payer' => $payer->id,
            'payee' => $payer->id,
            'amount' => 1000,
        ], [
            'Idempotency-Key' => 'same-payer-payee',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['payee']);
    }

    public function test_transient_authorizer_failure_returns_503_and_allows_retry(): void
    {
        Http::fake([
            'https://util.devi.tools/api/v2/authorize' => Http::sequence()
                ->push([], 503)
                ->push([
                    'data' => ['authorization' => true],
                ], 200),
        ]);
        Queue::fake();

        $payer = User::factory()->create();
        $payee = User::factory()->create();

        $payer->wallet->forceFill(['balance' => 10000])->save();

        Sanctum::actingAs($payer);

        $response = $this->postJson('/api/v1/transfer', [
            'payer' => $payer->id,
            'payee' => $payee->id,
            'amount' => 1000,
        ], [
            'Idempotency-Key' => 'transient-key',
        ]);

        $response->assertStatus(503)
            ->assertJsonPath('code', 'authorizer_unavailable');

        $this->assertDatabaseMissing('idempotency_keys', [
            'key' => 'transient-key',
        ]);

        $retry = $this->postJson('/api/v1/transfer', [
            'payer' => $payer->id,
            'payee' => $payee->id,
            'amount' => 1000,
        ], [
            'Idempotency-Key' => 'transient-key',
        ]);

        $retry->assertStatus(201);
    }

    public function test_replay_of_failed_transfer_returns_same_response(): void
    {
        Http::fake([
            'https://util.devi.tools/api/v2/authorize' => Http::response([
                'data' => ['authorization' => true],
            ], 200),
        ]);
        Queue::fake();

        $payer = User::factory()->create();
        $payee = User::factory()->create();

        $payer->wallet->forceFill(['balance' => 100])->save();

        Sanctum::actingAs($payer);

        $payload = [
            'payer' => $payer->id,
            'payee' => $payee->id,
            'amount' => 500,
        ];

        $first = $this->postJson('/api/v1/transfer', $payload, [
            'Idempotency-Key' => 'failed-replay',
        ]);

        $first->assertStatus(422)
            ->assertJsonPath('data.failure_reason', FailureReason::InsufficientFunds->value);

        $second = $this->postJson('/api/v1/transfer', $payload, [
            'Idempotency-Key' => 'failed-replay',
        ]);

        $second->assertStatus(422)
            ->assertJsonPath('data.id', $first->json('data.id'))
            ->assertJsonPath('data.failure_reason', FailureReason::InsufficientFunds->value);
    }

    public function test_replay_of_missing_payer_returns_persisted_transfer(): void
    {
        Http::fake([
            'https://util.devi.tools/api/v2/authorize' => Http::response([
                'data' => ['authorization' => true],
            ], 200),
        ]);
        Queue::fake();

        $payer = User::factory()->create();
        $payee = User::factory()->create();
        $payer->delete();

        Sanctum::actingAs($payer);

        $payload = [
            'payer' => $payer->id,
            'payee' => $payee->id,
            'amount' => 1000,
        ];

        $first = $this->postJson('/api/v1/transfer', $payload, [
            'Idempotency-Key' => 'missing-payer-replay',
        ]);

        $first->assertStatus(422)
            ->assertJsonPath('data.failure_reason', FailureReason::PayerNotFound->value);

        $second = $this->postJson('/api/v1/transfer', $payload, [
            'Idempotency-Key' => 'missing-payer-replay',
        ]);

        $second->assertStatus(422)
            ->assertJsonPath('data.id', $first->json('data.id'))
            ->assertJsonPath('data.failure_reason', FailureReason::PayerNotFound->value);
    }

    public function test_empty_idempotency_key_header_returns_422(): void
    {
        $payer = User::factory()->create();
        $payee = User::factory()->create();

        Sanctum::actingAs($payer);

        $response = $this->postJson('/api/v1/transfer', [
            'payer' => $payer->id,
            'payee' => $payee->id,
            'amount' => 1000,
        ], [
            'Idempotency-Key' => '',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['idempotency_key']);
    }

    public function test_legacy_decimal_value_field_is_rejected(): void
    {
        $payer = User::factory()->create();
        $payee = User::factory()->create();

        Sanctum::actingAs($payer);

        $response = $this->postJson('/api/v1/transfer', [
            'payer' => $payer->id,
            'payee' => $payee->id,
            'value' => '25.00',
        ], [
            'Idempotency-Key' => 'legacy-value',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['amount']);
    }
}
