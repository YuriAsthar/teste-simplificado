<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, string>|string>
     */
    public function rules(): array
    {
        return [
            'payer_wallet_id' => ['required', 'integer', 'exists:wallets,id'],
            'payee_wallet_id' => ['required', 'integer', 'exists:wallets,id', 'different:payer_wallet_id'],
            'amount_cents' => ['required', 'integer', 'min:1'],
            'idempotency_key' => ['required', 'string', 'max:255'],
        ];
    }
}
