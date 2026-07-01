# Services

## Overview
Business-logic services. The directory contains both the legacy Kafka/publisher flow and the new relational wallet transfer flow.

## Files
- `WalletTransferService.php` — Core wallet-to-wallet executor: database transaction, balance update, idempotency, failure recording, transient authorizer handling, and authorizer rejection (throws `AuthorizerRejectedException` so the request can be retried).
- `IdempotencyKeyService.php` — Builds idempotency fingerprints, acquires/resolves idempotency keys, finalizes keys for completed/failed transfers, deletes processing keys, and recovers stale `Processing` rows based on `transfer.idempotency_processing_ttl_seconds`.
- `LoginService.php` — Authenticates users and issues Sanctum tokens.
- `LogoutService.php` — Revokes the current Sanctum bearer token for the authenticated user.
- `AuthorizerClient.php` — External authorizer HTTP client. Returns an `App\Enums\AuthorizerResult` enum (`Authorized`, `Rejected`, `Transient`) and retries only on `ConnectionException`.
- `NotificationClient.php` — External notification HTTP client for transfer notifications.
- `KafkaTransferService.php` — Legacy service that publishes transfer messages and dispatches notification jobs (retained for messaging flow).
- `TransferMessageBuilder.php`, `TransferMessageConsumer.php`, `KafkaTransferProcessor.php`, `TransferRetryMessageConsumer.php`, `TransferRetryPolicy.php` — Kafka/RabbitMQ messaging plumbing.
- `Kafka/DryRunTransferPublisher.php`, `KafkaTransferPublisher.php` — Kafka publisher implementations.
- `DryRun/DryRunContext.php`, `DryRun/DryRunRecorder.php` — Dry-run helpers for messaging tests.

## Conventions
- Services hold business logic; controllers are thin.
- Wallet-transfer services operate on integer cents only.
- Use typed method signatures and constructor injection.
- Domain invariants (sufficient balance, currency match, ownership) belong in services.

## Related
- Parent: /app/agents.md
- Related: /app/Models/agents.md, /app/Http/Controllers/Api/V1/agents.md
