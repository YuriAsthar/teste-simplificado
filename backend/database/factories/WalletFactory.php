<?php

namespace Database\Factories;

use App\Enums\CurrencyType;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Wallet>
 */
class WalletFactory extends Factory
{
    /**
     * @var class-string<Wallet>
     */
    protected $model = Wallet::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'currency' => CurrencyType::BRA->value,
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(static function (Wallet $wallet): void {
            $wallet->forceFill(['balance' => 0])->save();
        });
    }
}
