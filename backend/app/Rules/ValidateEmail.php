<?php

declare(strict_types=1);

namespace App\Rules;

use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final readonly class ValidateEmail implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!is_string($value)) {
            $fail('Invalid email');

            return;
        }

        if (User::query()->where('email', $value)->exists()) {
            $fail('Invalid email');
        }
    }
}
