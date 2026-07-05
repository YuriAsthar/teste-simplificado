<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\CurrencyType;
use App\Enums\UserType;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    private const int INITIAL_BALANCE_CENTS = 100_000;

    public function run(): void
    {
        $this->createUser('Common User 1', 'common+1@example.com', UserType::Common);
        $this->createUser('Common User 2', 'common+2@example.com', UserType::Common);
        $this->createUser('Merchant User 1', 'merchant+1@example.com', UserType::Merchant);
        $this->createUser('Merchant User 2', 'merchant+2@example.com', UserType::Merchant);

        User::factory()
            ->count(6)
            ->afterCreating($this->createWallet(...))
            ->create();
    }

    private function createUser(string $name, string $email, UserType $type): User
    {
        $user = User::factory()->create([
            'name' => $name,
            'email' => $email,
            'password' => '12345678',
            'type' => $type,
        ]);

        $this->createWallet($user);

        return $user;
    }

    private function createWallet(User $user): void
    {
        Wallet::unguarded(static function () use ($user): void {
            $user->wallet()->create([
                'currency' => CurrencyType::BRA->value,
                'balance' => self::INITIAL_BALANCE_CENTS,
            ]);
        });
    }
}
