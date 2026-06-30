# Console Commands

## Overview
Artisan commands that support operational tasks for the wallet/transfer domain.

## Structure
| File/Folder | Purpose | Type |
|-------------|---------|------|
| `RetryNotificationsCommand.php` | Dispatches `SendTransferNotificationJob` for completed transfers pending notification within the last 30 days. | PHP |

## Conventions
- Commands extend `Illuminate\Console\Command` and are registered in `routes/console.php`.
- Business logic delegates to services; commands are thin orchestrators.

## Related
- Parent: /app/agents.md
- Related: /app/Jobs/agents.md
