# Unit Tests

## Overview
PHPUnit unit tests for isolated, fast components: services, jobs, and domain helpers.

## Structure
| File/Folder | Purpose | Type |
|-------------|---------|------|
| `Support/MoneyParserTest.php` | Tests decimal-string → integer-cents parsing, including edge cases and rejection of invalid formats. | PHP |
| `Casts/MoneyCastTest.php` | Tests `MoneyCast` strict int-in/int-out behavior and rejection of null, float, string, etc. | PHP |
| `Services/NotificationServiceTest.php` | Tests `NotificationService` using `Http::fake()` because the class is `final readonly`: success response, 204 No Content success, non-success HTTP status, non-success JSON status, connection failure, and payee email fallback. | PHP |
| `Jobs/SendNotificationJobTest.php` | Tests the unified notification job: marks transfers notified on success, throws `NotificationException` on failure, skips non-completed/already-notified/missing transfers. | PHP |
| `Services/WalletTransferServiceTest.php` | Tests the wallet-to-wallet transfer service: idempotency lock, request_hash mismatch, in-progress state, stale-key recovery, transient authorizer cleanup, authorizer rejection cleanup, replay, failure paths, and `SendNotificationJob` dispatch via `Queue::fake()`. | PHP |
| `Services/IdempotencyKeyServiceTest.php` | Tests idempotency request_hash fixed order and SHA-256 format. | PHP |
| `Services/KafkaTransferServiceTest.php` | Tests the legacy Kafka transfer service. | PHP |
| `Services/KafkaTransferProcessorTest.php` | Tests the transfer processor: idempotency guard, dispatch, missing/not-completed transfer handling, and dry-run early return with no side effects. | PHP |
| `Services/TransferMessageConsumerTest.php` | Tests the Kafka message consumer: valid consumption, DLQ for malformed messages, retry on failure, retry with attempt count, DLQ after max retries, and dry-run skipping of DLQ/retry publishes. | PHP |
| `Services/TransferMessageBuilderTest.php` | Tests the Kafka message builder. | PHP |
| `Services/TransferRetryPolicyTest.php` | Tests the retry policy. | PHP |

## Conventions
- Unit tests extend `Tests\TestCase`.
- Use Mockery for service dependencies; clean up in `tearDown()`.
- PHPStan baseline contains intentional Mockery-related ignores for test mocks.

## Related
- Parent: /app/tests/agents.md
- Related: /app/tests/Feature/agents.md
