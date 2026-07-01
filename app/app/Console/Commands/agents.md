# Console Commands

## Overview
Artisan commands that support operational tasks for the wallet/transfer domain.

## Structure
| File/Folder | Purpose | Type |
|-------------|---------|------|
| `RetryNotificationsCommand.php` | Dispatches `SendTransferNotificationJob` for completed transfers pending notification within the last 30 days. | PHP |
| `ConsumeTransfersCommand.php` | Consumes transfer messages from Kafka/RabbitMQ. | PHP |
| `ConsumeRetryTransfersCommand.php` | Consumes retry transfer messages from Kafka/RabbitMQ. | PHP |
| `KafkaProduceTransferCommand.php` | Produces transfer messages to Kafka for testing. | PHP |

## Conventions
- Commands extend `Illuminate\Console\Command` and are auto-discovered by Artisan via their `signature` property; `routes/console.php` contains closure-based commands only.
- Business logic delegates to services; commands are thin orchestrators.

## Related
- Parent: /app/agents.md
- Related: /app/Jobs/agents.md
