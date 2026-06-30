<?php

namespace Database\Factories;

use App\Enums\CurrencyType;
use App\Enums\TransferStatus;
use App\Models\Transfer;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Transfer>
 */
class TransferFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<Transfer>
     */
    protected $model = Transfer::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $payer = User::factory()->create();
        $payee = User::factory()->create();

        return [
            'user_id' => $payer->id,
            'payer_wallet_id' => $payer->wallet->id,
            'payee_wallet_id' => $payee->wallet->id,
            'amount_cents' => fake()->numberBetween(1, 100000),
            'currency' => CurrencyType::BRA->value,
            'idempotency_key' => fake()->uuid(),
            'status' => TransferStatus::Completed->value,
            'failure_reason' => null,
        ];
    }
}
