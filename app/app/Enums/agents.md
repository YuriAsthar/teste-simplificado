# Enums

## Overview
Backed enums representing domain values and status/lifecycle concepts.

## Files
- `CurrencyType.php` — Supported wallet currencies (`BRA`, `USD`, `EUR`).
- `DocumentType.php` — Document kinds accepted for user identity (`cpf`, `cnpj`, passport, driver license, national id).
- `FailureReason.php` — Reasons a transfer can fail (`insufficient_funds`, `payer_not_found`, `payee_not_found`, `invalid_amount`, `currency_mismatch`, `wallet_locked`, `idempotency_conflict`, `same_payer_and_payee`, `payer_is_merchant`, `wallet_inactive`, `authorizer_rejected`, `unknown`).
- `TransferStatus.php` — Transfer lifecycle states (`pending`, `authorized`, `completed`, `failed`, `cancelled`, `refunded`) with transition rules.
- `UserType.php` — User roles (`common`, `merchant`).

## Conventions
- Use string-backed enums for persistence compatibility.
- Enum-specific behavior (labels, descriptions, transitions) lives in small methods on the enum itself.

## Related
- Parent: /app/agents.md
