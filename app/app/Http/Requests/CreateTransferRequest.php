<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

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
            'value' => ['required', 'numeric', 'min:0.01'],
        ];
    }

    public function amountCents(): int
    {
        $value = (string) $this->validated('value');

        if (function_exists('bcmul')) {
            return (int) bcadd(bcmul($value, '100', 4), '0', 0);
        }

        return (int) round((float) $value * 100);
    }

    public function idempotencyKey(): ?string
    {
        $header = $this->header('Idempotency-Key');

        return is_string($header) && $header !== '' ? $header : null;
    }
}
