<?php

declare(strict_types=1);

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * @implements CastsAttributes<string|null, mixed>
 * @SuppressWarnings("PHPMD.UnusedFormalParameter")
 */
final class MoneyCast implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if (is_null($value)) {
            return null;
        }

        $cents = (int) $value;

        return $this->centsToDecimal($cents);
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?int
    {
        if (is_null($value)) {
            return null;
        }

        if (is_int($value)) {
            return $value;
        }

        if (is_string($value)) {
            return $this->decimalStringToCents($value);
        }

        if (is_float($value)) {
            return $this->decimalStringToCents((string) $value);
        }

        throw new InvalidArgumentException('Money cast cannot convert value of type ' . gettype($value));
    }

    private function centsToDecimal(int $cents): string
    {
        $negative = $cents < 0;
        $cents = abs($cents);
        $units = intdiv($cents, 100);
        $fraction = $cents % 100;

        $decimal = $units . '.' . str_pad((string) $fraction, 2, '0', STR_PAD_LEFT);

        return $negative ? '-' . $decimal : $decimal;
    }

    private function decimalStringToCents(string $value): int
    {
        $value = trim($value);

        if (!is_numeric($value)) {
            throw new InvalidArgumentException("Money cast value must be numeric, '{$value}' given.");
        }

        if (function_exists('bcmul')) {
            $rounded = bcadd(bcmul($value, '100', 4), '0', 0);

            return (int) $rounded;
        }

        return (int) round((float) $value * 100);
    }
}
