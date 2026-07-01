# Unit Tests

## Overview
PHPUnit unit tests for isolated, fast components: services, jobs, and domain helpers.

## Structure
| File/Folder | Purpose | Type |
|-------------|---------|------|
| `Support/MoneyParserTest.php` | Tests decimal-string → integer-cents parsing, including edge cases and rejection of invalid formats. | PHP |
| `Casts/MoneyCastTest.php` | Tests `MoneyCast` strict int-in/int-out behavior and rejection of null, float, string, etc. | PHP |
| `Jobs/SendNotificationJobTest.php` | Tests the legacy notification job marks transfers as notified on success/failure. | PHP |
| `Jobs/SendTransferNotificationJobTest.php` | Tests the relational-flow notification job. | PHP |
| `Services/AuthorizerClientTest.php` | Tests `AuthorizerClient` returns the `AuthorizerResult` enum for 2xx, 4xx, 5xx, and connection failures. | PHP |
| `Services/WalletTransferServiceTest.php` | Tests the wallet-to-wallet transfer service: idempotency lock, fingerprint mismatch, in-progress state, stale-key recovery, transient authorizer cleanup, replay, and failure paths. | PHP |
| `Services/IdempotencyKeyServiceTest.php` | Tests idempotency fingerprint fixed order and SHA-256 format. | PHP |
| `Services/KafkaTransferServiceTest.php` | Tests the legacy Kafka transfer service. | PHP |
| `Services/KafkaTransferProcessorTest.php` | Tests the RabbitMQ transfer processor. | PHP |
| `Services/TransferMessageConsumerTest.php` | Tests the Kafka message consumer. | PHP |
| `Services/TransferMessageBuilderTest.php` | Tests the Kafka message builder. | PHP |
| `Services/Kafka/DryRunTransferPublisherTest.php` | Tests the dry-run Kafka publisher. | PHP |
| `Services/DryRun/DryRunContextTest.php` | Tests the dry-run context helper. | PHP |
| `Services/TransferRetryMessageConsumerTest.php` | Tests the retry consumer. | PHP |
| `Services/TransferRetryPolicyTest.php` | Tests the retry policy. | PHP |

## Conventions
- Unit tests extend `Tests\TestCase`.
- Use Mockery for service dependencies; clean up in `tearDown()`.
- PHPStan baseline contains intentional Mockery-related ignores for test mocks.

## Related
- Parent: /app/tests/agents.md
- Related: /app/tests/Feature/agents.md
