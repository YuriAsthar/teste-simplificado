<?php

declare(strict_types=1);

namespace App\Support;

use InvalidArgumentException;

final readonly class MoneyParser
{
    /**
     * Parse a decimal money string into integer cents.
     *
     * Accepted formats: "10", "10.5", "10.50", "0.01".
     * Rejected: "10,50", "10.500", "10.999", "10.", ".50",
     * "-10.50", "1e2", "0x10", empty/whitespace, non-numeric.
     *
     * @throws InvalidArgumentException
     */
    public static function parseToCents(string $value): int
    {
        $value = trim($value);

        if ($value === '') {
            throw new InvalidArgumentException('Money value cannot be empty.');
        }

        if (!preg_match('/^\d+(\.\d{1,2})?$/', $value)) {
            throw new InvalidArgumentException("Invalid money value: '{$value}'.");
        }

        $parts = explode('.', $value);

        $whole = (int) $parts[0];
        $fraction = isset($parts[1])
            ? str_pad($parts[1], 2, '0', STR_PAD_RIGHT)
            : '00';

        return ($whole * 100) + (int) $fraction;
    }
}
