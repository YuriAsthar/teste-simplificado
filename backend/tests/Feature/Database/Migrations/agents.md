# Feature Tests: Database Migrations

## Overview
Feature-level tests that exercise specific migrations in isolation to verify schema changes and data backfills.

## Files
- `IdempotencyKeysMigrationBackfillTest.php` — Verifies the `2026_07_02_000000_add_status_and_request_hash_to_idempotency_keys` migration: legacy rows linked to a completed transfer receive `status = completed` and a SHA-256 request_hash of `payer_id:payee_id:amount`, while orphan rows receive `status = completed` with `request_hash = null`. The test migrates fresh, inserts legacy rows manually, then applies `migrate` and asserts the expected backfill.

## Conventions
- Migration tests use `migrate:fresh` to ensure a deterministic starting schema.
- Legacy rows are inserted via `DB::table()` to simulate pre-migration data.
- Assertions verify both the column/backfill values and the nullable request_hash behavior.

## Related
- Parent: /app/tests/Feature/agents.md
- Related: /app/database/migrations/agents.md
