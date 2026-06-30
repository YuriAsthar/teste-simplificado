# Form Requests

## Overview
Validation rules and authorization gates for incoming API requests.

## Files
- `CreateTransferRequest.php` — Validates `payer`, `payee`, decimal-string `value`, and `Idempotency-Key` header for transfers.
- `LoginRequest.php` — Validates credentials for token issuance.

## Conventions
- Every write endpoint has its own `FormRequest` class.
- Rules are explicit, typed, and use `exists` validations for relational integrity.
- Money request fields are validated as decimal strings and converted to integer cents via `App\Support\MoneyParser::parseToCents()` before passing to services.

## Related
- Parent: /app/agents.md
- Related: /app/Http/Controllers/Api/V1/agents.md
