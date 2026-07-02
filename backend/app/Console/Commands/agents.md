# Console Commands

## Overview
Artisan commands for operational tasks.

## Files
- `CleanupStaleIdempotencyKeysCommand.php` — `idempotency:cleanup-stale-keys`: deletes `Processing` idempotency key rows older than the configured TTL (`transfer.idempotency_processing_ttl_seconds`, default 300 seconds).
- `KafkaProduceTransferCommand.php` — `kafka:produce-transfer`: publishes a manual transfer event to Kafka (local dev/test only).

## Conventions
- Commands follow the Laravel 11 auto-discovery convention in `app/Console/Commands/`.
- Use typed signatures with `{argument}` and `{--option}` syntax.
- Return `self::SUCCESS` or `self::FAILURE`.

## Related
- Parent: /app/agents.md
- Related: /app/config/agents.md