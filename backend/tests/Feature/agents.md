# Feature Tests

## Overview
PHPUnit feature tests covering end-to-end HTTP flows, console commands, and model behavior.

## Structure
| File/Folder | Purpose | Type |
|-------------|---------|------|
| `Api/V1/TransferControllerTest.php` | End-to-end tests for `POST /api/v1/transfer`: idempotency replay, request_hash mismatch, transient 503, failed-transfer replay, missing-payer replay, empty idempotency key validation, and business-rule failures. | PHP |
| `Api/V1/LoginControllerTest.php` | Tests `POST /api/v1/auth/login` issuance. | PHP |
| `Console/RetryNotificationsCommandTest.php` | Tests the `notifications:retry` command dispatches pending jobs. | PHP |
| `RelationalPaymentModelDodTest.php` | Model-level data-integrity tests for users, wallets, transfers, money cast, and status transitions. | PHP |
| `Kafka/*.php` | Kafka integration tests (skipped when broker unavailable). | PHP |
| `Console/*.php` | Console command tests: `RetryNotificationsCommandTest.php`, `ConsumeTransfersCommandTest.php`. | PHP |
| `Database/Migrations/IdempotencyKeysMigrationBackfillTest.php` | Verifies the idempotency keys migration backfills `status` and `request_hash` for legacy rows. | PHP |

## Conventions
- Feature tests use `LazilyRefreshDatabase` for DB state.
- External HTTP calls are faked with `Http::fake()`; queues/events are faked as needed.
- Tests reference model properties via `getKey()` and explicit `@var` annotations for PHPStan.

## Related
- Parent: /app/tests/agents.md
- Related: /app/tests/Unit/agents.md
