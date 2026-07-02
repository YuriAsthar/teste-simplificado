<?php

declare(strict_types=1);

namespace App\Enums;

enum UserType: string
{
    case Common = 'common';
    case Merchant = 'merchant';

    public function isMerchant(): bool
    {
        return $this === self::Merchant;
    }
}
