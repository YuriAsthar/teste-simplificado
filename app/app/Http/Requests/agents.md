# Form Requests

## Overview
Validation rules and authorization gates for incoming API requests.

## Files
- `CreateTransferRequest.php` — Validates `payer_wallet_id`, `payee_wallet_id`, `amount_cents`, and `idempotency_key` for transfers.

## Conventions
- Every write endpoint has its own `FormRequest` class.
- Rules are explicit, typed, and use `exists` validations for relational integrity.

## Related
- Parent: /app/agents.md
- Related: /app/Http/Controllers/Api/V1/agents.md
