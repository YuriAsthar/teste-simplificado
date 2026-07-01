<?php

declare(strict_types=1);

namespace App\Rules;

use App\Validators\CpfCnpjValidator;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final readonly class CnpjRule implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!is_string($value)) {
            $fail('The :attribute field is not a valid CNPJ.')->translate();

            return;
        }

        if (!CpfCnpjValidator::isValidCnpj($value)) {
            $fail('The :attribute field is not a valid CNPJ.')->translate();
        }
    }
}
