# Form Requests

## Overview
Validation rules and authorization gates for incoming API requests.

## Files
- `CreateTransferRequest.php` — Validates integer `payer`/`payee` references and integer `amount` (`> 0`). The `Idempotency-Key` header is required and non-empty; `prepareForValidation()` merges it into `idempotency_key` so it participates in normal validation.
- `LoginRequest.php` — Validates credentials for token issuance.

## Conventions
- Every write endpoint has its own `FormRequest` class.
- Rules are explicit, typed, and use `exists` validations for relational integrity.
- API money fields are validated as integer cents directly (`amount` is `required|integer|gt:0`); no `MoneyParser` conversion is used for the transfer endpoint.

## Related
- Parent: /app/agents.md
- Related: /app/Http/Controllers/Api/V1/agents.md
