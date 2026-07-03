<?php

declare(strict_types=1);

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * @implements CastsAttributes<int, int>
 */
final class MoneyCast implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): int
    {
        if (is_null($value)) {
            throw new InvalidArgumentException("Money cast cannot return null for non-nullable column '{$key}'.");
        }

        return (int) $value;
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): int
    {
        if (!is_int($value)) {
            throw new InvalidArgumentException(
                "Money cast only accepts int cents for '{$key}', " . gettype($value) . ' given.'
            );
        }

        return $value;
    }
}
