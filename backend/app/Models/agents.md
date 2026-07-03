# Models

## Overview
Eloquent models for the relational wallet/transfer domain.

## Files
- `User.php` — Authenticatable entity with soft deletes, document triple (`document_country`, `document_type`, `document_value`), `UserType` cast, and a `HasOne` wallet relation.
- `Wallet.php` — Balance/currency container with soft deletes, `MoneyCast` on `balance`, and transfer relations. `Wallet::$fillable` does NOT include `balance`; balance is guarded and set only through the model under `MoneyCast`.
- `Transfer.php` — Transfer record with `TransferStatus` and `FailureReason` backed-enum casts, idempotency key, and wallet/user relations. Recent migrations relaxed FK constraints so failed transfers can persist non-existent payer/payee references and `amount = 0`.
- `IdempotencyKey.php` — Idempotency lock record with a `IdempotencyKeyStatus` backed-enum cast (`Processing`, `Completed`), SHA-256 `request_hash`, and an optional `belongsTo(Transfer::class)` relation. The relation is optional (nullable `transfer_id`) because the `relaxed_transfer_constraints_for_failed_records` migration does not require every failed transfer row to exist in `users`/`wallets`.

## Conventions
- Models use backed enum casts for domain values.
- Money is stored as integer cents and cast via `MoneyCast`.
- Wallet balance must be updated with integer cents because `MoneyCast` rejects non-int assignments.
- Soft deletes are used on `User` and `Wallet`.
- Scopes are prefixed with `scope` and typed with generics.

## Model Properties
- All models use traditional `protected $fillable` and `protected $hidden` properties.
- Do **not** use Laravel 13 `#[Fillable(...)]` or `#[Hidden(...)]` attributes; they are functionally equivalent but create inconsistency with the rest of the project.

## Related
- Parent: /app/agents.md
- Related: /app/Casts/agents.md, /app/Enums/agents.md, /app/database/migrations/agents.md
