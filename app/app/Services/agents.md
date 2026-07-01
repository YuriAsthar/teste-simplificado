# Services

## Overview
Business-logic services. The directory contains both the legacy Kafka/publisher flow and the new relational wallet transfer flow.

## Files
- `WalletTransferService.php` — Core wallet-to-wallet executor: cache locks, database transaction, balance update, idempotency, failure recording.
- `LoginService.php` — Authenticates users and issues Sanctum tokens.
- `AuthorizerClient.php` — External authorizer HTTP client with retry on connection failure.
- `NotificationClient.php` — External notification HTTP client for transfer notifications.
- `TransferService.php` — Legacy service that publishes transfer messages and dispatches notification jobs (retained for messaging flow).
- `TransferMessageBuilder.php`, `TransferMessageConsumer.php`, `TransferProcessor.php`, `TransferRetryMessageConsumer.php`, `TransferRetryPolicy.php` — Kafka/RabbitMQ messaging plumbing.
- `Kafka/DryRunTransferPublisher.php`, `KafkaTransferPublisher.php` — Kafka publisher implementations.
- `DryRun/DryRunContext.php`, `DryRun/DryRunRecorder.php` — Dry-run helpers for messaging tests.

## Conventions
- Services hold business logic; controllers are thin.
- Wallet-transfer services operate on integer cents only; decimal input parsing happens in the FormRequest layer via `MoneyParser`.
- Use typed method signatures and constructor injection.
- Domain invariants (sufficient balance, currency match, ownership) belong in services.

## Related
- Parent: /app/agents.md
- Related: /app/Models/agents.md, /app/Http/Controllers/Api/V1/agents.md
