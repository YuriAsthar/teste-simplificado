<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Enums\CurrencyType;
use App\Events\UserCreated;
use App\Models\Wallet;

final readonly class CreateUserWallet
{
    public function handle(UserCreated $event): void
    {
        $event->user->wallet()->firstOrCreate(
            ['user_id' => $event->user->id],
            [
                'balance_cents' => 0,
                'currency' => CurrencyType::BRA->value,
            ]
        );
    }
}
