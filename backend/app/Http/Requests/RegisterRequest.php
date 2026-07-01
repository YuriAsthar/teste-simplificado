<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\DocumentType;
use App\Enums\UserType;
use App\Rules\CnpjRule;
use App\Rules\CpfRule;
use App\Rules\ValidateEmail;
use App\ValueObjects\DocumentData;
use App\ValueObjects\DocumentValueNormalizer;
use App\ValueObjects\RegisterData;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Validator;

final class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $type = $this->input('document_type');
        $value = $this->input('document_value');

        if (!is_string($type) || !is_string($value)) {
            return;
        }

        $this->merge([
            'document_value' => DocumentValueNormalizer::normalize($type, $value),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:255'],
            'email' => ['required', 'string', 'email', new ValidateEmail()],
            'password' => ['required', 'string', 'min:6', 'confirmed'],
            'password_confirmation' => ['required_with:password'],
            'type' => ['sometimes', Rule::enum(UserType::class)],
            'document_country' => ['required', 'string', 'alpha', 'size:3'],
            'document_type' => ['required', 'string', Rule::enum(DocumentType::class)],
            'document_value' => array_merge(
                ['required', 'string', 'max:255', 'not_regex:/[\x00-\x1F\x7F]/'],
                $this->documentValueRules(),
            ),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.email' => 'Invalid email',
        ];
    }

    /**
     * @return list<object>
     */
    private function documentValueRules(): array
    {
        return match ($this->input('document_type')) {
            DocumentType::BrCpf->value => [new CpfRule()],
            DocumentType::BrCnpj->value => [new CnpjRule()],
            default => [],
        };
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $country = $this->input('document_country');
            $type = $this->input('document_type');

            if (!filled($country) || !filled($type)) {
                return;
            }

            $documentType = DocumentType::tryFrom((string) $type);

            if (is_null($documentType)) {
                return;
            }

            $allowedTypes = DocumentType::allowedForCountry((string) $country);

            if (!in_array($documentType, $allowedTypes, true)) {
                $validator->errors()->add(
                    'document_type',
                    'The selected document type is invalid for the given country.',
                );
            }
        });
    }

    public function registerData(): RegisterData
    {
        $typeValue = $this->validated('type') ?? UserType::Common->value;

        return new RegisterData(
            name: $this->validated('name'),
            email: $this->validated('email'),
            password: $this->validated('password'),
            type: UserType::from($typeValue),
            document: $this->documentData(),
        );
    }

    private function documentData(): DocumentData
    {
        return new DocumentData(
            country: (string) $this->validated('document_country'),
            type: DocumentType::from((string) $this->validated('document_type')),
            value: (string) $this->validated('document_value'),
        );
    }
}
