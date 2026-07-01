<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Enums\CurrencyType;
use App\Enums\FailureReason;
use App\Enums\TransferStatus;
use App\Enums\UserType;
use App\Jobs\SendTransferNotificationJob;
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
            'value' => '25.00',
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
            'value' => '25.00',
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
            'value' => '25.00',
        ], [
            'Idempotency-Key' => 'transfer-1',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.status', TransferStatus::Completed->value)
            ->assertJsonPath('data.failure_reason', null);

        $this->assertSame(7500, (int) $payer->fresh()?->wallet->getRawOriginal('balance'));
        $this->assertSame(2500, (int) $payee->fresh()?->wallet->getRawOriginal('balance'));

        Queue::assertPushed(SendTransferNotificationJob::class);
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
            'value' => '5.00',
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
            'value' => '10.00',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('data.failure_reason', FailureReason::PayerIsMerchant->value);
    }

    public function test_it_returns_failed_transfer_when_authorizer_rejects(): void
    {
        Http::fake([
            'https://util.devi.tools/api/v2/authorize' => Http::response([
                'data' => ['authorization' => false],
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
            'value' => '10.00',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('data.failure_reason', FailureReason::AuthorizerRejected->value);
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
            'value' => '10.00',
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
            'value' => '10.00',
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
            'value' => '10.00',
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

    public function test_it_returns_validation_error_when_payer_is_missing(): void
    {
        $payer = User::factory()->create();

        Sanctum::actingAs($payer);

        $response = $this->postJson('/api/v1/transfer', [
            'payee' => $payer->id,
            'value' => '10.00',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['payer']);
    }

    public function test_it_returns_validation_error_when_value_is_invalid(): void
    {
        $payer = User::factory()->create();
        $payee = User::factory()->create();

        Sanctum::actingAs($payer);

        $response = $this->postJson('/api/v1/transfer', [
            'payer' => $payer->id,
            'payee' => $payee->id,
            'value' => '0',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['value']);
    }

    public function test_it_returns_validation_error_when_payer_and_payee_are_same(): void
    {
        $payer = User::factory()->create();

        Sanctum::actingAs($payer);

        $response = $this->postJson('/api/v1/transfer', [
            'payer' => $payer->id,
            'payee' => $payer->id,
            'value' => '10.00',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['payee']);
    }
}
