<?php

declare(strict_types=1);

namespace App\Rules;

use App\Validators\CpfCnpjValidator;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final readonly class CpfRule implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!is_string($value)) {
            $fail('The :attribute field is not a valid CPF.')->translate();

            return;
        }

        if (!CpfCnpjValidator::isValidCpf($value)) {
            $fail('The :attribute field is not a valid CPF.')->translate();
        }
    }
}
