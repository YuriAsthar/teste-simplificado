<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class CreateTransferRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $mergeData = [
            'idempotency_key' => $this->header('Idempotency-Key'),
        ];

        if (!$this->has('payer')) {
            $mergeData['payer'] = $this->user()?->id;
        }

        $this->merge($mergeData);
    }

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
            'amount' => ['required', 'integer', 'gt:0'],
            'idempotency_key' => ['required', 'string', 'min:1'],
        ];
    }

    /**
     * @return array<string, array<int, string>|string>
     */
    public function messages(): array
    {
        return [
            'amount.gt' => 'The amount must be greater than 0.',
            'idempotency_key.required' => 'The Idempotency-Key header is required.',
        ];
    }

    public function amount(): int
    {
        return (int) $this->validated('amount');
    }

    public function idempotencyKey(): string
    {
        return (string) $this->validated('idempotency_key');
    }
}
