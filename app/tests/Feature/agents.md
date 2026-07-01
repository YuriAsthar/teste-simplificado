# Feature Tests

## Overview
PHPUnit feature tests covering end-to-end HTTP flows, console commands, and model behavior.

## Structure
| File/Folder | Purpose | Type |
|-------------|---------|------|
| `Api/V1/TransferControllerTest.php` | Tests POST /api/transfer authentication, validation, and transfer outcomes. | PHP |
| `Api/V1/TokenControllerTest.php` | Tests POST /api/auth/token issuance. | PHP |
| `Console/RetryNotificationsCommandTest.php` | Tests the `notifications:retry` command dispatches pending jobs. | PHP |
| `RelationalPaymentModelDodTest.php` | Model-level data-integrity tests for users, wallets, transfers, money cast, and status transitions. | PHP |
| `Kafka/*.php` | Kafka integration tests (skipped when broker unavailable). | PHP |
| `Console/*.php` | Console command tests: `RetryNotificationsCommandTest.php`, `ConsumeTransfersCommandTest.php`, `ConsumeRetryTransfersCommandTest.php`, `KafkaProduceTransferCommandTest.php`. | PHP |

## Conventions
- Feature tests use `LazilyRefreshDatabase` for DB state.
- External HTTP calls are faked with `Http::fake()`; queues/events are faked as needed.
- Tests reference model properties via `getKey()` and explicit `@var` annotations for PHPStan.

## Related
- Parent: /app/tests/agents.md
- Related: /app/tests/Unit/agents.md
