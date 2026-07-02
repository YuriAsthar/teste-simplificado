# Form Requests

## Overview
Validation rules and authorization gates for incoming API requests.

## Files
- `CreateTransferRequest.php` — Validates integer `payer`/`payee` references and integer `amount` (> 0). The `Idempotency-Key` header is required and non-empty; `prepareForValidation()` merges it into `idempotency_key` so it participates in normal validation.
- `LoginRequest.php` — Validates credentials for token issuance.
- `RegisterRequest.php` — Validates registration payload including email uniqueness. Both the `email` format rule and the `ValidateEmail` custom rule return the same generic English message (`Invalid email`) to prevent user enumeration.

## Conventions
- Every write endpoint has its own `FormRequest` class.
- Rules are explicit, typed, and use `exists` validations for relational integrity.
- API money fields are validated as integer cents directly (`amount` is `required|integer|gt:0`); no `MoneyParser` conversion is used for the transfer endpoint.
- Custom validation messages are declared in `messages()` when the default Laravel wording would leak sensitive information or conflict with project terminology.

## Related
- Parent: /app/agents.md
- Related: /app/Http/Controllers/Api/V1/agents.md
