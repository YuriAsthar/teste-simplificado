# Console Commands

## Overview
Artisan commands for operational tasks.

## Files
- `CleanupStaleIdempotencyKeysCommand.php` — `idempotency:cleanup-stale-keys`: deletes `Processing` idempotency key rows older than the configured TTL (`transfer.idempotency_processing_ttl_seconds`, default 300 seconds).
- `ConsumeTransfersCommand.php` — `kafka:consume-transfers`: consumes transfer events from Kafka with `--dry-run` and `--daemon` support. Dry-run mode skips offset commits and side effects and logs skipped actions to the console. Daemon mode limits the consumer to `kafka.topics.wallet.transfer.completed.limit_messages` messages per run.
- `RetryNotificationsCommand.php` — `notifications:retry`: dispatches `SendNotificationJob` for all completed transfers with a pending notification from the last 30 days.

## Conventions
- Commands follow the Laravel 11 auto-discovery convention in `app/Console/Commands/`.
- Use typed signatures with `{argument}` and `{--option}` syntax.
- Return `self::SUCCESS` or `self::FAILURE`.

## Related
- Parent: /app/agents.md
- Related: /app/config/agents.md