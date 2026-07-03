# Kafka Usage in Wallet Sandbox

This document describes how Apache Kafka is used in this local-first Laravel wallet/transfer application.

## Overview

Kafka publishes transfer events so downstream consumers can react asynchronously. The producer and consumer use a canonical envelope format, Redis-backed idempotency, and a dead-letter queue (DLQ) for malformed or unprocessable messages.

Transfer events are not published directly from the HTTP request. Instead, the application uses the Transactional Outbox pattern: the transfer write and the event write happen inside the same database transaction, and a separate publisher process sends the events to Kafka.

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
| `wallet.transfer.retry` | Retry topic (escrito pelo consumidor, mas **não consumido** neste sandbox) |

Configure them in `config/kafka.php` or via `.env`:

```dotenv
KAFKA_TOPIC_COMPLETED=wallet.transfer.completed
KAFKA_TOPIC_DLQ=wallet.transfer.dlq
KAFKA_TOPIC_RETRY=wallet.transfer.retry
KAFKA_RETRY_ATTEMPTS=3
KAFKA_RETRY_BACKOFF_SECONDS=60
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
- Does **not** publish retry or DLQ messages.
- Logs each skipped action as `[DRY-RUN] ...` via `Log::info` / `Log::warning`.

## Dry-run summary

The `kafka:consume-transfers` command supports `--dry-run`:

| Command | `--dry-run` behavior |
|---------|----------------------|
| `kafka:consume-transfers` | Skips RabbitMQ dispatch, idempotency write, and offset commits. |

**Important:** Dry-run output may include PII or financial data (`payer_id`, `payee_id`, `amount_cents`, `transfer_id`) depending on the payload. Treat `--dry-run` output with the same care as production logs and avoid sharing it in public channels.

## Retry Flow

When the main consumer (`kafka:consume-transfers`) fails to process a transfer, it does not drop the message. Instead, it checks `meta.retry.attempt` (default `0`) and either:

1. Publishes a new message to `wallet.transfer.retry` with `meta.retry.attempt` incremented and `meta.retry.scheduled_at` set to `now + KAFKA_RETRY_BACKOFF_SECONDS`.
2. Sends the original message to `wallet.transfer.dlq` when the attempt limit is reached.

**This sandbox does not consume the `wallet.transfer.retry` topic.** Messages published to it remain in the topic until they are inspected manually (e.g., via `kafka-console-consumer`) or consumed by an external system. For production, add a dedicated retry worker command that reads from `wallet.transfer.retry` and reprocesses the transfers.

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

The consumer commits offsets **manually after successful processing**. This gives at-least-once delivery semantics.

**Trade-off:**
- If the handler succeeds but the commit fails (crash, network error), the message will be redelivered and the Redis idempotency marker will skip the duplicate side effects.
- The downside is that repeated commit failures can cause reprocessing until the idempotency TTL expires.

## Daemon mode

Pass `--daemon` to run the consumer with `withMaxMessages()` set to `config('kafka.topics.wallet.transfer.completed.limit_messages', 100)`. Without `--daemon`, the consumer blocks naturally on `consume()` until the process is stopped.

**Removed settings:** `KAFKA_TOPIC_COMPLETED_DELAY` and the per-topic `delay` field were removed because the previous sleep-based batch loop was replaced by a single long-lived consumer process.

## Testing

Integration tests run against a real Kafka broker. Configure the broker in `.env.testing` or pass it at runtime:

```dotenv
KAFKA_INTEGRATION_BROKER=kafka:9092
```

Run the suite:

```bash
docker compose run --rm app composer test
```

Tests are skipped when `KAFKA_INTEGRATION_BROKER` is empty or points to `127.0.0.1:9092`.

## Transactional Outbox Pattern

This application implements the Transactional Outbox pattern to guarantee that every completed transfer is eventually published to Kafka.

### Why the outbox is needed

Publishing directly to Kafka inside the HTTP request would create a **dual-write problem**: the code would first commit the database transaction and then attempt to publish to Kafka. If Kafka is temporarily unavailable, or if the publish call fails after the DB commit, the transfer would be persisted but no event would be emitted. Conversely, publishing before the DB commit could emit an event for a transfer that later fails authorization or balance validation.

The outbox solves this by making the event write part of the same database transaction as the transfer write. The transfer row and the outbox row are committed together atomically. A separate, asynchronous process then reads the outbox table and publishes the events to Kafka.

### Pipeline

```
HTTP request
   │
   ▼
WalletTransferService ──► DB transaction
   │                        │
   │                        ├── Transfer row (created/updated)
   │                        └── OutboxEvent row (Pending)
   │                            │
   ▼                            ▼
Response to caller      outbox:publish (scheduled every minute)
                               │
                               ▼
                        TransferPublisherInterface
                               │
                               ▼
                           Kafka topic
                      wallet.transfer.completed
                               │
                               ▼
                        kafka:consume-transfers
                               │
                               ▼
                        KafkaTransferProcessor
                               │
                               ▼
                     SendNotificationJob on RabbitMQ
                               │
                               ▼
                        NotificationService
                               │
                               ▼
                    POST to external notifier
```

Step by step:

1. `WalletTransferService::execute` runs the transfer inside a `DB::transaction` block.
2. When the transfer completes, it inserts an `OutboxEvent` with `status = Pending`.
3. The transaction commits; either both the transfer and the outbox row exist, or neither does.
4. The scheduled `outbox:publish --batch=100` command runs every minute, reads pending events in `created_at` order, builds the Kafka envelope, and publishes them.
5. On success, the command marks the row `Published`. On failure, it marks it `Failed`, increments `attempts`, and records `last_error_at`.
6. `kafka:consume-transfers` receives the event, and `KafkaTransferProcessor` dispatches `SendNotificationJob` to the `rabbitmq` queue connection.
7. A RabbitMQ worker runs the job, which calls `NotificationService` to POST to the external notifier.

### `outbox_events` table and `OutboxEvent` model

The table is created by `database/migrations/2026_07_02_200000_create_outbox_events_table.php`:

| Column | Purpose |
|--------|---------|
| `aggregate_type` | Domain aggregate the event belongs to (`transfer`) |
| `aggregate_id` | Aggregate primary key (`transfer.id`) |
| `event_type` | Event name (`transfer.completed`) |
| `payload` | JSON envelope data used to build the Kafka message |
| `status` | `Pending`, `Published`, or `Failed` |
| `attempts` | Number of failed publish attempts |
| `last_error_at` | Timestamp of the most recent failure, used for retry backoff |

The unique index on `(aggregate_type, aggregate_id, event_type)` prevents duplicate event rows for the same transfer. The compound indexes on `(status, created_at)` and `(status, attempts, last_error_at)` support the scheduled publisher query.

The `OutboxEvent` model provides a `pending()` Eloquent scope that selects rows eligible for publishing:

- Status is `Pending` or `Failed`.
- `attempts` is below `config('outbox.max_attempts', 3)`.
- Either the row has never failed, or `last_error_at` is older than `config('outbox.retry_interval_seconds', 300)`.

The model also exposes `markPublished()` and `markFailed()` helpers that the command uses to update state.

### `outbox:publish` command

```bash
docker compose run --rm app php artisan outbox:publish {--batch=100}
```

`PublishOutboxEventsCommand` reads pending outbox rows, builds the Kafka message through `TransferMessageBuilder`, and publishes via `TransferPublisherInterface`. The default `--batch=100` limits how many events a single run processes.

The command is registered in `routes/console.php` to run every minute with `withoutOverlapping(10)`, so overlapping runs are skipped for up to 10 minutes:

```php
app(Schedule::class)
    ->command('outbox:publish --batch=100')
    ->everyMinute()
    ->withoutOverlapping(10);
```

Configure retry behavior in `config/outbox.php` or via `.env`:

```dotenv
OUTBOX_MAX_ATTEMPTS=3
OUTBOX_RETRY_INTERVAL_SECONDS=300
```

### Why not publish directly from the HTTP request

Direct publication couples the HTTP response to Kafka availability and creates the dual-write problem described above. With the outbox:

- The HTTP request finishes as soon as the DB transaction commits, independent of Kafka latency or availability.
- Kafka publish failures can be retried by the scheduled command without re-running the transfer logic.
- The event log stays consistent with the database state because both are committed together.

The trade-off is a small delay (up to one minute in the default schedule) between a completed transfer and the Kafka event. For this sandbox, that is acceptable and removes the need for distributed transactions or exactly-once Kafka semantics inside the request handler.

## Scope and Limitations

This Kafka setup is intentionally simple and **local-dev only**:

- No Schema Registry.
- No SASL/SSL.
- No exactly-once semantics.
- Retry scheduling is produced by the main consumer, but it is **not consumed in this sandbox**. For production, add a separate retry worker and scheduler.

For production use, add authentication, schema management, bounded retry scheduling, and monitoring before enabling this in a real environment.
