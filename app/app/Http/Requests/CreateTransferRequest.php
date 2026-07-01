<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Support\MoneyParser;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use InvalidArgumentException;

final class CreateTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>|string>
     */
    public function rules(): array
    {
        return [
            'payer' => ['required', 'integer', 'exists:users,id'],
            'payee' => ['required', 'integer', 'exists:users,id', 'different:payer'],
            'value' => ['required', 'string', 'regex:/^\d+(\.\d{1,2})?$/'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $value = $this->input('value');

            if (!is_string($value)) {
                return;
            }

            try {
                if (MoneyParser::parseToCents($value) <= 0) {
                    $validator->errors()->add('value', 'The value must be greater than 0.');
                }
            } catch (InvalidArgumentException) {
                // The regex rule already covers invalid format; do not duplicate errors.
            }
        });
    }

    public function amountCents(): int
    {
        return MoneyParser::parseToCents($this->validated('value'));
    }

    public function idempotencyKey(): ?string
    {
        $header = $this->header('Idempotency-Key');

        return is_string($header) && $header !== '' ? $header : null;
    }
}
