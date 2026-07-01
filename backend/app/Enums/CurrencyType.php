<?php

declare(strict_types=1);

namespace App\Enums;

enum CurrencyType: string
{
    case BRA = 'BRA';
    case USD = 'USD';
    case EUR = 'EUR';

    public function label(): string
    {
        return match ($this) {
            self::BRA => 'Brazilian Real',
            self::USD => 'US Dollar',
            self::EUR => 'Euro',
        };
    }
}
