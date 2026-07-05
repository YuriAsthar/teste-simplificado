# CLI Commands Reference

This document lists all custom Artisan commands in the wallet application.

## Custom Commands

### `outbox:publish`

Publishes pending outbox events to Kafka using the Transactional Outbox pattern.

**Signature:**
```bash
php artisan outbox:publish {--batch=100}
```

**Options:**
- `--batch`: Number of events to process per run (default: 100)

**Purpose:**
Reads pending `OutboxEvent` rows, builds Kafka messages, and publishes them to the `wallet.transfer.completed` topic. Marks events as `Published` on success or `Failed` with retry metadata on failure.

**Example:**
```bash
docker compose run --rm app php artisan outbox:publish
docker compose run --rm app php artisan outbox:publish --batch=50
```

---

### `kafka:consume-transfers`

Consumes transfer events from the Kafka `wallet.transfer.completed` topic.

**Signature:**
```bash
php artisan kafka:consume-transfers {--dry-run} {--daemon}
```

**Options:**
- `--dry-run`: Simulate without committing offsets or side effects
- `--daemon`: Run continuously in daemon mode with per-topic batch configuration

**Purpose:**
Processes Kafka messages, dispatches `SendNotificationJob` to RabbitMQ, and manages idempotency guards. In dry-run mode, logs actions without persisting any changes or committing offsets.

**Example:**
```bash
# Normal consumption
docker compose run --rm app php artisan kafka:consume-transfers

# Dry-run mode (no side effects)
docker compose run --rm app php artisan kafka:consume-transfers --dry-run

# Daemon mode (continuous)
docker compose run --rm app php artisan kafka:consume-transfers --daemon
```

---

### `notifications:retry`

Retries notifications for completed transfers from the last 30 days.

**Signature:**
```bash
php artisan notifications:retry
```

**Purpose:**
Dispatches `SendNotificationJob` for all completed transfers with pending notifications. Uses the `Transfer::pendingNotification()` scope to find transfers that have not been notified yet.

**Example:**
```bash
docker compose run --rm app php artisan notifications:retry
```

---

### `idempotency:cleanup-stale-keys`

Deletes idempotency keys stuck in the processing state longer than the configured TTL.

**Signature:**
```bash
php artisan idempotency:cleanup-stale-keys
```

**Purpose:**
Cleans up stale `IdempotencyKey` rows with status `Processing` that have exceeded the TTL defined in `transfer.idempotency_processing_ttl_seconds` (default: 300 seconds). This prevents locks from being held indefinitely.

**Example:**
```bash
docker compose run --rm app php artisan idempotency:cleanup-stale-keys
```

---

### `schedule:run` (Laravel core)

Runs scheduled tasks that are due.

**Signature:**
```bash
php artisan schedule:run
```

**Purpose:**
Executes any tasks defined in `routes/console.php` that are due to run. In this application, `outbox:publish` is scheduled to run every minute.

**Example:**
```bash
docker compose run --rm app php artisan schedule:run
```

---

## Standard Laravel Commands

### `migrate`

Run database migrations.

**Example:**
```bash
docker compose run --rm app php artisan migrate
docker compose run --rm app php artisan migrate --force
docker compose run --rm app php artisan migrate --env=testing
```

### `migrate:rollback`

Rollback the last database migration.

**Example:**
```bash
docker compose run --rm app php artisan migrate:rollback
docker compose run --rm app php artisan migrate:rollback --step=1
```

### `migrate:fresh`

Drop all tables and re-run all migrations.

**Example:**
```bash
docker compose run --rm app php artisan migrate:fresh
docker compose run --rm app php artisan migrate:fresh --seed
```

### `key:generate`

Generate the application encryption key.

**Example:**
```bash
docker compose run --rm app php artisan key:generate
```

### `queue:work`

Start processing jobs on the queue.

**Example:**
```bash
# Process RabbitMQ jobs
docker compose run --rm app php artisan queue:work rabbitmq

# Process specific queue
docker compose run --rm app php artisan queue:work rabbitmq --queue=default

# Run with specified tries and timeout
docker compose run --rm app php artisan queue:work rabbitmq --tries=3 --timeout=90
```

### `queue:listen`

Listen for jobs on the queue.

**Example:**
```bash
docker compose run --rm app php artisan queue:listen rabbitmq
```

### `queue:retry`

Retry a failed queue job by ID.

**Example:**
```bash
docker compose run --rm app php artisan queue:retry <job-id>
```

### `cache:clear`

Clear the application cache.

**Example:**
```bash
docker compose run --rm app php artisan cache:clear
```

### `config:clear`

Clear the configuration cache.

**Example:**
```bash
docker compose run --rm app php artisan config:clear
```

### `route:clear`

Clear the route cache.

**Example:**
```bash
docker compose run --rm app php artisan route:clear
```

### `route:cache`

Cache the routes.

**Example:**
```bash
docker compose run --rm app php artisan route:cache
```

### `view:clear`

Clear the compiled view cache.

**Example:**
```bash
docker compose run --rm app php artisan view:clear
```

---

## Test Commands

### `test`

Run the application tests.

**Example:**
```bash
# Run all tests
docker compose run --rm app composer test

# Run specific test
docker compose run --rm app ./vendor/bin/phpunit --filter=WalletTransferServiceTest

# Run tests with detailed output
docker compose run --rm app ./vendor/bin/phpunit --verbose

# Stop on first failure
docker compose run --rm app ./vendor/bin/phpunit --stop-on-failure
```

---

## Development Commands

### `tinker`

Interact with the application via an interactive REPL.

**Example:**
```bash
docker compose run --rm app php artisan tinker
```

### `list`

List all Artisan commands.

**Example:**
```bash
docker compose run --rm app php artisan list
docker compose run --rm app php artisan list | grep kafka
```

---

## Notes

- All custom commands use `docker compose run --rm` (never `exec`)
- The `queue:work` command should be run continuously to process RabbitMQ jobs
- The `outbox:publish` command is scheduled to run every minute via the scheduler
- The `kafka:consume-transfers` command can be run in daemon mode for continuous consumption