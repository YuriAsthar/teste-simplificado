<?php

namespace Database\Factories;

use App\Enums\CurrencyType;
use App\Enums\TransferStatus;
use App\Models\Transfer;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory\u003cTransfer\u003e
 */
class TransferFactory extends Factory
{
    /**
     * @var class-string\u003cTransfer\u003e
     */
    protected $model = Transfer::class;

    /**
     * @return array\u003cstring, mixed\u003e
     */
    public function definition(): array
    {
        $payer = User::factory();
        $payee = User::factory();

        return [
            'payer_id' => $payer,
            'payee_id' => $payee,
            'amount' => fake()->numberBetween(1, 100000),
            'currency' => CurrencyType::BRA->value,
            'idempotency_key' => fake()->uuid(),
            'status' => TransferStatus::Completed->value,
            'failure_reason' => null,
            'notified_at' => null,
        ];
    }
}
