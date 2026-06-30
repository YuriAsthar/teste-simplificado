# Migrations

## Overview
Database schema definitions for users, wallets, transfers, cache, and jobs.

## Files
- `0001_01_01_000000_create_users_table.php` — Users table with document triple, soft deletes, and a partial unique index on active documents.
- `2026_06_30_110400_create_wallets_table.php` — Wallets table with user foreign key, integer-cents balance, currency, and soft deletes.
- `2026_06_30_110500_create_transfers_table.php` — Transfers table with payer/payee wallet foreign keys, amount, currency, idempotency key, status, and failure reason.
- `0001_01_01_000001_create_cache_table.php`, `0001_01_01_000002_create_jobs_table.php` — Laravel cache and queue tables.

## Conventions
- Money columns are `bigInteger` storing integer cents.
- Foreign keys use `constrained()` with cascade rules where appropriate.
- Partial indexes use PostgreSQL-specific `WHERE deleted_at IS NULL`; production targets PostgreSQL.

## Related
- Parent: /app/agents.md
- Related: /app/Models/agents.md
