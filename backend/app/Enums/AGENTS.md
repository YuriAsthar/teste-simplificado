# Enums

## Overview
Backed enums representing domain values and status/lifecycle concepts.

## Files
- `CurrencyType.php` — Supported wallet currencies (`BRA`, `USD`, `EUR`).
- `DocumentType.php` — Stripe-standard tax ID codes by region (Americas, Europe/EU, Asia-Pacific, Middle East/Africa), with `values()` and `allowedForCountry()` helpers and Eloquent cast support.
- `FailureReason.php` — Reasons a transfer can fail (`insufficient_funds`, `payer_not_found`, `payee_not_found`, `invalid_amount`, `currency_mismatch`, `wallet_locked`, `idempotency_conflict`, `same_payer_and_payee`, `payer_is_merchant`, `wallet_inactive`, `authorizer_rejected`, `unknown`).
- `TransferStatus.php` — Transfer lifecycle states (`pending`, `authorized`, `completed`, `failed`, `cancelled`, `refunded`) with transition rules.
- `UserType.php` — User roles (`common`, `merchant`).
- `IdempotencyKeyStatus.php` — Idempotency row lifecycle (`processing`, `completed`).
- `AuthorizerResult.php` — Result of an external authorization attempt (`authorized`, `rejected`, `transient`). This enum lives under `App\Enums`; it was moved from `App\Services` as part of the idempotency/transfer work.

## Conventions
- Use string-backed enums for persistence compatibility.
- Enum-specific behavior (labels, descriptions, transitions) lives in small methods on the enum itself.

## Related
- Parent: /app/AGENTS.md
