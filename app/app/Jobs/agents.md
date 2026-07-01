# Jobs

## Overview
Queueable job classes responsible for asynchronous side-effects, primarily transfer notification delivery.

## Structure
| File/Folder | Purpose | Type |
|-------------|---------|------|
| `SendNotificationJob.php` | Legacy notification job used by the Kafka/RabbitMQ messaging flow. | PHP |
| `SendTransferNotificationJob.php` | New relational-flow notification job that calls the notifier API and marks transfers as notified. | PHP |

## Conventions
- Jobs implement `ShouldQueue` and use the standard Laravel queue traits.
- Notification jobs receive only the `transferId` and resolve the transfer inside `handle()`.
- `NotificationClient` is injected via method injection in `handle()`.
- Use `forceFill()` for datetime columns to avoid PHPStan property-type warnings.

## Related
- Parent: /app/agents.md
