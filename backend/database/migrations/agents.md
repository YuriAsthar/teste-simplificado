# Migrations

## Overview
Database schema definitions for users, wallets, transfers, cache, jobs, and idempotency keys.

## Files
- `0001_01_01_000000_create_users_table.php` — Users table with document triple, soft deletes, and a partial unique index on active documents.
- `2026_06_30_110400_create_wallets_table.php` — Wallets table with user foreign key, integer-cents `balance`, currency, and soft deletes.
- `2026_06_30_110500_create_transfers_table.php` — Transfers table with payer/payee wallet foreign keys, amount, currency, idempotency key, status, and failure reason.
- `2026_06_30_171500_update_transfers_table_for_user_transfers.php` — Updates transfers table for user-level transfers.
- `2026_06_30_171600_create_idempotency_keys_table.php` — Creates idempotency keys table for transfer deduplication.
- `2026_06_30_201621_rename_balance_and_amount_columns.php` — Renames `balance_cents` → `balance` and `amount_cents` → `amount`; adds CHECK constraints.
- `2026_07_02_000000_add_status_and_fingerprint_to_idempotency_keys.php` — Adds `status` and `fingerprint` columns, indexes both, and backfills legacy rows: linked transfers become `Completed` with a SHA-256 fingerprint of `payer_id:payee_id:amount`; rows without a transfer become `Completed` with `fingerprint = null`.
- `2026_07_02_100000_relax_transfer_constraints_for_failed_records.php` — Removes foreign keys on `transfers.payer_id` and `transfers.payee_id` and relaxes the amount check to `>= 0` so failed transfer records can persist missing users and invalid amounts.
- `0001_01_01_000001_create_cache_table.php`, `0001_01_01_000002_create_jobs_table.php` — Laravel cache and queue tables.

## Conventions
- Money columns are `bigInteger` storing integer cents. Final column names: `wallets.balance` and `transfers.amount` (both bigint cents).
- Foreign keys use `constrained()` with cascade rules where appropriate.
- Partial indexes use PostgreSQL-specific `WHERE deleted_at IS NULL`; production targets PostgreSQL.
- Do not use `Blueprint::after()`; add new columns at the end of the table.

## Related
- Parent: /app/agents.md
- Related: /app/Models/agents.md
