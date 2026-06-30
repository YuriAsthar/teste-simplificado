# Kafka Usage in Wallet Sandbox

This document describes how Apache Kafka is used in this local-first Laravel wallet/transfer application.

## Overview

Kafka publishes transfer events so downstream consumers can react asynchronously. The producer and consumer use a canonical envelope format, Redis-backed idempotency, and a dead-letter queue (DLQ) for malformed or unprocessable messages.

## Kafka vs RabbitMQ Roles

| Bus | Responsibility | Example |
|-----|----------------|---------|
| **Kafka** | Transfer business events | `wallet.transfer.completed` emitted after a transfer is authorized |
| **RabbitMQ** | Notification jobs | `SendNotificationJob` dispatched via the `rabbitmq` queue connection |

Keeping the two buses separate makes the event log (Kafka) independent from the notification workload (RabbitMQ).

## Setup

Start the Kafka broker with the rest of the stack:

```bash
docker compose up -d kafka
```

The `docker-compose.yml` enables `KAFKA_AUTO_CREATE_TOPICS_ENABLE: 'true'`, so the topics below are created automatically when first used.

## Topic Names

| Topic | Purpose |
|-------|---------|
| `wallet.transfer.completed` | Successfully authorized transfers |
| `wallet.transfer.dlq` | Dead-letter queue for malformed/failed messages |
| `wallet.transfer.retry` | Retry topic for failed transfer events |

Configure them in `app/config/kafka.php` or via `.env`:

```dotenv
KAFKA_TOPIC_COMPLETED=wallet.transfer.completed
KAFKA_TOPIC_DLQ=wallet.transfer.dlq
KAFKA_TOPIC_RETRY=wallet.transfer.retry
KAFKA_RETRY_ATTEMPTS=3
KAFKA_RETRY_BACKOFF_SECONDS=60
KAFKA_CONSUMER_GROUP_ID_RETRY=wallet-transfer-consumers-retry
```

## Message Envelope

Every Kafka message uses the same envelope structure:

```json
{
  "meta": {
    "version": "1.0",
    "event": "transfer.authorized",
    "occurred_at": "2026-06-29T12:34:56-03:00"
  },
  "payload": {
    "transfer_id": "txn_a1b2c3d4e5f67890",
    "payer_id": 1,
    "payee_id": 2,
    "amount_cents": 5000,
    "occurred_at": "2026-06-29T12:34:56-03:00"
  }
}
```

The Kafka message key is the `transfer_id`, which guarantees ordering for a single transfer.

## Commands

### Produce a manual transfer event

```bash
docker compose run --rm app php artisan kafka:produce-transfer {payer_id} {payee_id} {amount_cents}
```

Example:

```bash
docker compose run --rm app php artisan kafka:produce-transfer 1 2 5000
```

All arguments must be positive integers. This command is intended for **local development and manual testing only**; it bypasses authorization, balance checks, and API validation.

#### Preview mode with `--dry-run`

Add `--dry-run` to print the envelope without actually publishing to Kafka:

```bash
docker compose run --rm app php artisan kafka:produce-transfer 1 2 5000 --dry-run
```

In dry-run mode the command builds the message envelope and prints the topic, key, and JSON envelope, but it does not send anything to Kafka.

### Consume transfer events

```bash
docker compose run --rm app php artisan kafka:consume-transfers
```

The consumer reads from `wallet.transfer.completed`, validates the payload, dispatches a `SendNotificationJob` to RabbitMQ, and stores an idempotency marker in Redis.

#### Preview mode with `--dry-run`

```bash
docker compose run --rm app php artisan kafka:consume-transfers --dry-run
```

In dry-run mode the consumer:

- Does **not** commit Kafka offsets.
- Does **not** dispatch the `SendNotificationJob` to RabbitMQ.
- Does **not** write the Redis idempotency marker.
- Prints each action that would have happened as `[DRY-RUN] {action}: {context_json}`.

### Consume retry events

```bash
docker compose run --rm app php artisan kafka:consume-retry-transfers
```

The retry consumer reads from `wallet.transfer.retry`. Each retry message carries `meta.retry.attempt` and `meta.retry.scheduled_at`. The consumer sleeps until `scheduled_at` is reached, then reprocesses the transfer. If reprocessing fails, the message is either retried again or sent to the DLQ once `KAFKA_RETRY_ATTEMPTS` is exceeded.

#### Preview mode with `--dry-run`

```bash
docker compose run --rm app php artisan kafka:consume-retry-transfers --dry-run
```

In dry-run mode the retry consumer:

- Does **not** sleep until the scheduled time.
- Still calls `TransferProcessor` so you can preview what would happen, but `TransferProcessor` does not dispatch or write idempotency markers.
- Does **not** commit Kafka offsets.
- Prints each recorded action as `[DRY-RUN] {action}: {context_json}`.

## Dry-run summary

All three Kafka artisan commands support `--dry-run`:

| Command | `--dry-run` behavior |
|---------|----------------------|
| `kafka:produce-transfer` | Builds and prints the envelope; does not publish to Kafka. |
| `kafka:consume-transfers` | Skips RabbitMQ dispatch, idempotency write, and offset commits. |
| `kafka:consume-retry-transfers` | Skips the scheduled wait, RabbitMQ dispatch, idempotency write, and offset commits. |

**Important:** Dry-run output may include PII or financial data (`payer_id`, `payee_id`, `amount_cents`, `transfer_id`) depending on the payload. Treat `--dry-run` output with the same care as production logs and avoid sharing it in public channels.

## Retry Flow

When the main consumer (`kafka:consume-transfers`) fails to process a transfer, it does not drop the message. Instead, it checks `meta.retry.attempt` (default `0`) and either:

1. Publishes a new message to `wallet.transfer.retry` with `meta.retry.attempt` incremented and `meta.retry.scheduled_at` set to `now + KAFKA_RETRY_BACKOFF_SECONDS`.
2. Sends the original message to `wallet.transfer.dlq` when the attempt limit is reached.

The retry consumer (`kafka:consume-retry-transfers`) then waits until `scheduled_at` before reprocessing the transfer. Malformed retry messages (missing `meta`, missing `retry` metadata, or unparseable `scheduled_at`) are sent directly to the DLQ.

Retries are **bounded**: `KAFKA_RETRY_ATTEMPTS` defaults to `3`, so the sequence is attempt `0` (original) → `1` → `2` → `3` → DLQ. The delay between each retry is `KAFKA_RETRY_BACKOFF_SECONDS` (default `60`).

## Idempotency

`TransferProcessor` stores a Redis key for every successfully processed transfer:

```
kafka:transfer:{transfer_id}
```

TTL is controlled by `KAFKA_IDEMPOTENCY_TTL` (default 3600 seconds). This prevents duplicate notification jobs if Kafka redelivers the same message or if a retry is published for a transfer that was already processed.

**Trade-offs:**
- If Redis is flushed or the TTL expires while a duplicate message is still in flight, the consumer will process the transfer again.
- Idempotency is keyed on the application `transfer_id`, not on Kafka's internal message key or offset.

## Offset Commit

The consumer commits offsets **manually after successful processing** when `KAFKA_COMMIT_AFTER_HANDLE=true` (default). This gives at-least-once delivery semantics.

**Trade-off:**
- If the handler succeeds but the commit fails (crash, network error), the message will be redelivered and the Redis idempotency marker will skip the duplicate side effects.
- The downside is that repeated commit failures can cause reprocessing until the idempotency TTL expires.

Set `KAFKA_COMMIT_AFTER_HANDLE=false` to fall back to auto-commit.

## Testing

Integration tests run against a real Kafka broker. Configure the broker in `app/.env.testing` or pass it at runtime:

```dotenv
KAFKA_INTEGRATION_BROKER=kafka:9092
```

Run the suite:

```bash
docker compose run --rm app composer test
```

Tests are skipped when `KAFKA_INTEGRATION_BROKER` is empty or points to `127.0.0.1:9092`.

## Scope and Limitations

This Kafka setup is intentionally simple and **local-dev only**:

- No Schema Registry.
- No SASL/SSL.
- No Outbox pattern.
- No exactly-once semantics.
- Retry scheduling relies on a consumer sleeping until `scheduled_at`, so a single long delay blocks that consumer partition. For production, move retry scheduling to an external queue or scheduler.

For production use, add authentication, schema management, an Outbox table, bounded retry scheduling, and monitoring before enabling this in a real environment.
