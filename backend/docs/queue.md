# Queue & Jobs Documentation

This document describes the RabbitMQ queue setup, job processing, and retry flow for notifications.

## Queue Connection

### RabbitMQ Configuration

The application uses RabbitMQ as the primary queue driver for sending notifications. The queue connection is configured in `config/queue.php`:

```php
'rabbitmq' => [
    'driver' => 'rabbitmq',
    'queue' => env('RABBITMQ_QUEUE', 'default'),
    'connection' => 'default',
    'hosts' => [
        [
            'host' => env('RABBITMQ_HOST', '127.0.0.1'),
            'port' => (int) env('RABBITMQ_PORT', 5672),
            'user' => env('RABBITMQ_USER', 'guest'),
            'password' => env('RABBITMQ_PASSWORD', 'guest'),
            'vhost' => env('RABBITMQ_VHOST', '/'),
        ],
    ],
],
```

### Environment Variables

Configure RabbitMQ in `.env`:

```dotenv
RABBITMQ_HOST=db
RABBITMQ_PORT=5672
RABBITMQ_USER=guest
RABBITMQ_PASSWORD=guest
RABBITMQ_VHOST=/
RABBITMQ_QUEUE=default
```

### Docker Setup

Start the RabbitMQ service:

```bash
docker compose up -d rabbitmq
```

The RabbitMQ Management UI is available at `http://localhost:15672` (username: `guest`, password: `guest`).

---

## SendNotificationJob

### Purpose

`SendNotificationJob` handles sending notifications for completed transfers asynchronously. It calls the external notifier API and marks the transfer as notified on success.

### Job Configuration

```php
final class SendNotificationJob implements ShouldQueue
{
    public int $tries = 3;

    public array $backoff = [10, 30, 60];

    public function __construct(public readonly int $transferId)
    {
        $this->onConnection('rabbitmq');
    }
}
```

**Settings:**
- **Connection:** `rabbitmq` (explicitly set)
- **Tries:** 3 attempts before permanent failure
- **Backoff:** Exponential backoff with delays: 10s, 30s, 60s

### Job Logic

The job follows this logic flow:

1. **Find Transfer:** Loads the transfer with its payee relation
2. **Skip Missing:** If transfer not found, logs warning and returns
3. **Skip Notified:** If `notified_at` is already set, logs info and returns
4. **Skip Non-Completed:** If transfer status is not `Completed`, logs info and returns
5. **Send Notification:** Calls `NotificationService::notifyTransfer()`
6. **Mark Notified:** Updates `notified_at` to current timestamp on success

### Idempotency Guards

The job implements two levels of idempotency:

1. **`notified_at` Check:** Skips already-notified transfers
2. **Status Check:** Only processes `Completed` transfers

This ensures that the same transfer is never notified more than once.

### Error Handling

- **Retry on Failure:** If `NotificationException` is thrown, the job will retry based on the backoff schedule
- **Permanent Failure:** Logs detailed error in the `failed()` hook after all retries are exhausted

---

## Queue Worker

### Starting the Worker

Run the queue worker to process RabbitMQ jobs:

```bash
# Standard worker
docker compose run --rm app php artisan queue:work rabbitmq

# With configuration
docker compose run --rm app php artisan queue:work rabbitmq --queue=default --tries=3 --timeout=90
```

**Important:** Run the queue worker with `docker compose run --rm` (never `exec`).

### Worker Behavior

- Processes jobs from the `rabbitmq` connection
- Uses the `default` queue
- Respects job-specific `tries` and `backoff` settings
- Supports supervisor or process managers for production deployment

---

## Retry Flow

### notifications:retry Command

The `notifications:retry` command recovers missed notifications:

```bash
docker compose run --rm app php artisan notifications:retry
```

**Purpose:**
Dispatches `SendNotificationJob` for all completed transfers from the last 30 days that have not been notified yet.

**Implementation:**
Uses the `Transfer::pendingNotification()` scope to find eligible transfers:

```php
Transfer::query()
    ->pendingNotification()
    ->chunkById(100, function ($transfers) {
        foreach ($transfers as $transfer) {
            dispatch(new SendNotificationJob($transfer->getKey()));
        }
    });
```

**When to Use:**
- After RabbitMQ downtime or worker restarts
- When jobs were lost due to queue failure
- For manual recovery of missed notifications

### Automatic Retry vs Manual Retry

| Scenario | Mechanism |
|----------|-----------|
| **Temporary API failure** | Automatic job retry with exponential backoff |
| **RabbitMQ downtime** | Manual retry via `notifications:retry` |
| **Job lost in queue** | Manual retry via `notifications:retry` |

---

## Notification Service

### Purpose

`NotificationService` sends HTTP POST requests to the external notifier:

```php
final readonly class NotificationService
{
    public function notifyTransfer(Transfer $transfer): void
    {
        $payload = [
            'transfer_id' => $transfer->id,
            'payee_email' => $transfer->payee->email,
            'amount' => $transfer->amount,
            'status' => $transfer->status->value,
        ];

        $response = Http::post($this->url, $payload);

        if (!$response->successful()) {
            throw new NotificationException();
        }
    }
}
```

### External Notifier Configuration

Configure the notifier in `.env`:

```dotenv
services.notifier.url=https://util.devi.tools/api/v1/notify
```

### Success Conditions

The notification is considered successful when:
- HTTP response code is 2xx
- Response body is empty (204 No Content) **OR**
- Response body JSON contains `status: "success"`

### Failure Conditions

The notification throws `NotificationException` when:
- HTTP response code is not 2xx
- Response body JSON does not contain `status: "success"`
- Connection failure or network error

---

## Monitoring & Debugging

### RabbitMQ Management UI

Access the RabbitMQ Management UI at `http://localhost:15672`:

**Key Metrics:**
- **Queues tab:** View queue depth, message rates, and consumer connections
- **Connections tab:** Monitor active connections
- **Channels tab:** View active channels
- **Message Rates:** Track publish and consume rates

### Logging

The application logs key events:

```bash
# View queue worker logs
docker compose logs queue

# View application logs
docker compose logs app
```

**Log Examples:**
- `Notification sent successfully` - Successful notification
- `Notification attempt failed` - Job will retry
- `Notification job failed permanently` - All retries exhausted
- `Transfer not found for notification` - Missing transfer
- `Notification already sent; skipping` - Idempotency hit

### Failed Jobs

Failed jobs are stored in the `failed_jobs` table. View them:

```bash
docker compose run --rm app php artisan queue:failed

# Retry a specific failed job
docker compose run --rm app php artisan queue:retry <job-id>

# Clear all failed jobs
docker compose run --rm app php artisan queue:flush
```

---

## Best Practices

1. **Always use `docker compose run --rm`** for queue worker commands
2. **Monitor RabbitMQ queue depth** to prevent backlogs
3. **Use exponential backoff** for external service calls
4. **Implement idempotency guards** to prevent duplicate notifications
5. **Log detailed context** for debugging failures
6. **Run `notifications:retry`** after infrastructure downtime
7. **Use supervisor** for production worker management

---

## Troubleshooting

### Jobs Not Processing

**Check:**
1. RabbitMQ container is running: `docker compose ps rabbitmq`
2. Queue worker is running: `docker compose ps queue`
3. Queue has jobs: Check RabbitMQ Management UI
4. Worker logs: `docker compose logs queue`

### High Queue Depth

**Possible Causes:**
1. External notifier is slow or down
2. Worker not processing jobs fast enough
3. Network issues

**Solutions:**
1. Scale up workers: Run multiple worker processes
2. Check external notifier status
3. Review worker logs for errors

### Duplicate Notifications

**Prevention:**
1. The `notified_at` guard prevents duplicates
2. The status check ensures only completed transfers are notified
3. Redis idempotency key in Kafka consumer prevents duplicate dispatches

**Debug:**
- Check `transfers.notified_at` values
- Verify job dispatch logic
- Review Kafka consumer logs