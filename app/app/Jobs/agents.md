# Jobs

## Overview
Queueable job classes responsible for asynchronous side-effects, primarily transfer notification delivery.

## Structure
| File/Folder | Purpose | Type |
|-------------|---------|------|
| `SendNotificationJob.php` | Unified notification job. Resolves the transfer, skips missing/non-completed/already-notified records, calls `NotificationService::notifyTransfer()`, and marks the transfer as notified on success. Uses `rabbitmq` connection, 3 tries, exponential backoff `[10, 30, 60]`. Dispatched via `DB::afterCommit()` in `WalletTransferService`; if RabbitMQ is unavailable at commit time the job is never enqueued. Missed notifications are recovered by the `notifications:retry` command using the `Transfer::pendingNotification()` scope. | PHP |

## Conventions
- Jobs implement `ShouldQueue` and use the standard Laravel queue traits.
- Notification jobs receive only the `transferId` and resolve the transfer inside `handle()`.
- `NotificationService` is injected via method injection in `handle()`.
- Use `forceFill()` for datetime columns to avoid PHPStan property-type warnings.
- Throw `App\Exceptions\NotificationException` from the service so the queue worker can retry; log permanent failure in `failed()`.

## Related
- Parent: /app/agents.md
