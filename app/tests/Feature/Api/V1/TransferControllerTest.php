<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Enums\CurrencyType;
use App\Enums\FailureReason;
use App\Enums\TransferStatus;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

final class TransferControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_it_executes_transfer_between_wallets(): void
    {
        $payer = User::factory()->create();
        $payee = User::factory()->create();

        $payer->wallet->update(['balance_cents' => 10000]);

        $response = $this->postJson('/api/v1/transfers', [
            'payer_wallet_id' => $payer->wallet->id,
            'payee_wallet_id' => $payee->wallet->id,
            'amount_cents' => 2500,
            'idempotency_key' => 'transfer-1',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.status', TransferStatus::Completed->value)
            ->assertJsonPath('data.failure_reason', null);

        $this->assertSame(7500, (int) $payer->fresh()->wallet->getRawOriginal('balance_cents'));
        $this->assertSame(2500, (int) $payee->fresh()->wallet->getRawOriginal('balance_cents'));
    }

    public function test_duplicate_idempotency_key_returns_existing_transfer(): void
    {
        $payer = User::factory()->create();
        $payee = User::factory()->create();

        $payer->wallet->update(['balance_cents' => 10000]);

        $payload = [
            'payer_wallet_id' => $payer->wallet->id,
            'payee_wallet_id' => $payee->wallet->id,
            'amount_cents' => 1000,
            'idempotency_key' => 'duplicate-key',
        ];

        $first = $this->postJson('/api/v1/transfers', $payload);
        $second = $this->postJson('/api/v1/transfers', $payload);

        $first->assertStatus(201);
        $second->assertStatus(201);
        $this->assertSame(
            $first->json('data.id'),
            $second->json('data.id'),
        );
    }

    public function test_it_returns_failed_transfer_when_payer_has_insufficient_funds(): void
    {
        $payer = User::factory()->create();
        $payee = User::factory()->create();

        $payer->wallet->update(['balance_cents' => 100]);

        $response = $this->postJson('/api/v1/transfers', [
            'payer_wallet_id' => $payer->wallet->id,
            'payee_wallet_id' => $payee->wallet->id,
            'amount_cents' => 500,
            'idempotency_key' => 'insufficient-funds',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.status', TransferStatus::Failed->value)
            ->assertJsonPath('data.failure_reason', FailureReason::InsufficientFunds->value);
    }

    public function test_it_returns_failed_transfer_when_currencies_mismatch(): void
    {
        $payer = User::factory()->create();
        $payee = User::factory()->create();

        $payer->wallet->update(['balance_cents' => 10000]);
        $payee->wallet->update(['currency' => CurrencyType::USD->value]);

        $response = $this->postJson('/api/v1/transfers', [
            'payer_wallet_id' => $payer->wallet->id,
            'payee_wallet_id' => $payee->wallet->id,
            'amount_cents' => 1000,
            'idempotency_key' => 'currency-mismatch',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.status', TransferStatus::Failed->value)
            ->assertJsonPath('data.failure_reason', FailureReason::CurrencyMismatch->value);
    }

    public function test_it_returns_validation_error_when_payer_wallet_does_not_exist(): void
    {
        $payee = User::factory()->create();

        $response = $this->postJson('/api/v1/transfers', [
            'payer_wallet_id' => 99999,
            'payee_wallet_id' => $payee->wallet->id,
            'amount_cents' => 1000,
            'idempotency_key' => 'missing-payer',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['payer_wallet_id']);
    }

    public function test_it_returns_validation_error_when_amount_is_invalid(): void
    {
        $payer = User::factory()->create();
        $payee = User::factory()->create();

        $response = $this->postJson('/api/v1/transfers', [
            'payer_wallet_id' => $payer->wallet->id,
            'payee_wallet_id' => $payee->wallet->id,
            'amount_cents' => 0,
            'idempotency_key' => 'invalid-amount',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['amount_cents']);
    }

    public function test_it_returns_validation_error_when_wallets_are_the_same(): void
    {
        $payer = User::factory()->create();

        $response = $this->postJson('/api/v1/transfers', [
            'payer_wallet_id' => $payer->wallet->id,
            'payee_wallet_id' => $payer->wallet->id,
            'amount_cents' => 1000,
            'idempotency_key' => 'same-wallet',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['payee_wallet_id']);
    }

    public function test_it_returns_validation_error_for_missing_fields(): void
    {
        $response = $this->postJson('/api/v1/transfers', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors([
                'payer_wallet_id',
                'payee_wallet_id',
                'amount_cents',
                'idempotency_key',
            ]);
    }
}
