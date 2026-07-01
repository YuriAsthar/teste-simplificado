# Models

## Overview
Eloquent models for the relational wallet/transfer domain.

## Files
- `User.php` — Authenticatable entity with soft deletes, document triple (`document_country`, `document_type`, `document_value`), `UserType` cast, and a `HasOne` wallet relation.
- `Wallet.php` — Balance/currency container with soft deletes, `MoneyCast` on `balance`, and transfer relations. `Wallet::$fillable` does NOT include `balance`; balance is guarded and set only through the model under `MoneyCast`.
- `Transfer.php` — Transfer record with status transitions, failure reasons, idempotency key, and wallet/user relations.

## Conventions
- Models use backed enum casts for domain values.
- Money is stored as integer cents and cast via `MoneyCast`.
- Wallet balance must be updated with integer cents because `MoneyCast` rejects non-int assignments.
- Soft deletes are used on `User` and `Wallet`.
- Scopes are prefixed with `scope` and typed with generics.

## Related
- Parent: /app/agents.md
- Related: /app/Casts/agents.md, /app/Enums/agents.md, /app/database/migrations/agents.md
