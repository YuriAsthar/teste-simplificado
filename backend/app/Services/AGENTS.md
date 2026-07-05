# Services

## Overview
Business-logic services. The directory contains both the legacy Kafka/publisher flow and the new relational wallet transfer flow.

## Files
- `WalletTransferService.php` — Core wallet-to-wallet executor: database transaction, balance update, idempotency, failure recording, transient authorizer handling, authorizer rejection (throws `AuthorizerRejectedException` so the request can be retried), and transactional outbox insertion. Instead of dispatching `SendNotificationJob` directly, it creates an `OutboxEvent` record (`aggregate_type='transfer', aggregate_id=$transfer->id, event_type='transfer.completed'`) inside the same DB transaction. A scheduled `outbox:publish` command later publishes pending events to Kafka. The legacy `KafkaTransferService` direct-publish path was removed because the outbox is now the canonical mechanism; Kafka and RabbitMQ remain as the Kafka consumer bridge and notification worker respectively.
- `IdempotencyKeyService.php` — Builds idempotency request hashes, acquires/resolves idempotency keys, finalizes keys for completed/failed transfers (storing `endpoint`, `request_hash`, `response_status`, `response_body`), deletes processing keys, and recovers stale `Processing` rows based on `transfer.idempotency_processing_ttl_seconds`. On replay it returns cached `response_status` + `response_body` when the hash matches.
- `TransferMessageBuilder.php` — Builds Kafka envelopes with event `transfer.completed` and the real `transfer.id` as the message key.
- `LoginService.php` — Authenticates users and returns the authenticated `User` plus a new Sanctum `NewAccessToken` when credentials are valid.
- `LogoutService.php` — Revokes the current Sanctum bearer token for the authenticated user.
- `AuthorizerClient.php` — External authorizer HTTP client. Returns an `App\Enums\AuthorizerResult` enum (`Authorized`, `Rejected`, `Transient`) and retries only on `ConnectionException`.
- `NotificationService.php` — `final readonly` external notification HTTP client; sends a POST to `services.notifier.url` for completed transfers and throws `App\Exceptions\NotificationException` on non-2xx HTTP status or connection failure. Empty 204 No Content responses are accepted as success; if the response body is present, the JSON `status` field must equal `success`. Logs dispatch, success, and failure events with transfer/payee context.
- `KafkaTransferProcessor.php` — Kafka consumer bridge: uses Redis `kafka:transfer:{transfer_id}` as a pre-dispatch idempotency guard, dispatches `SendNotificationJob` on RabbitMQ, and marks messages processed when the transfer is missing or not completed to avoid endless redelivery. Accepts an optional `$dryRun` parameter to log skipped actions without side effects.
- `TransferMessageConsumer.php` — Consumes decoded Kafka messages, extracts `transfer_id`, logs processing start/success/failure, publishes retries via `TransferRetryPolicy`, and sends exhausted messages to the DLQ. Accepts an optional `$dryRun` parameter to log and skip retry/DLQ publishes.
- `TransferRetryPolicy.php` — Decides whether to retry a failed transfer, logs retry increments and max-retry events, and publishes retry/DLQ messages via the configured `TransferPublisherInterface`.
- `TransferMessageBuilder.php`, `KafkaTransferPublisher.php` — Kafka/RabbitMQ messaging plumbing.

## Conventions
- Services hold business logic; controllers are thin.
- Wallet-transfer services operate on integer cents only.
- Use typed method signatures and constructor injection.
- Domain invariants (sufficient balance, currency match, ownership) belong in services.
- `TransferController` maps `AuthorizerRejectedException` to HTTP 422, `TransientAuthorizerException` to HTTP 503, and identity mismatch to HTTP 403.
- `outbox:publish` runs every minute via scheduler with `WithoutOverlapping` to prevent concurrent execution in production.
- **Logging**: Always use `use Illuminate\Support\Facades\Log;` at the top of any file that calls `Log::`. Inside `App\Services` (or any namespace other than the root), an unqualified `Log` resolves to `App\Services\Log`, causing a "Class not found" error. The facade import is mandatory even if `Psr\Log\LoggerInterface` is also imported.

## Related
- Parent: ./AGENTS.md
- Related: ./Models/AGENTS.md, ../Http/Controllers/Api/V1/AGENTS.md
