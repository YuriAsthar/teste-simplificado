# Laravel Application

## Overview
Laravel 13 sandbox application implementing a relational wallet/transfer payment domain on top of PostgreSQL, Redis, RabbitMQ and Kafka infrastructure.

## Structure
| File/Folder | Purpose | Type |
|-------------|---------|------|
| `app/Enums/` | Backed enums: CurrencyType, UserType, DocumentType, TransferStatus, FailureReason | PHP |
| `app/Casts/MoneyCast.php` | Integer-cents money caster | PHP |
| `app/Models/User.php` | User with soft deletes, document triple and wallet relation | PHP |
| `app/Models/Wallet.php` | Wallet balance/currency with soft deletes | PHP |
| `app/Models/Transfer.php` | Transfer lifecycle and status transitions | PHP |
| `app/Services/WalletTransferService.php` | Wallet transfer executor (locks + transaction) | PHP |
| `app/Http/Controllers/Api/V1/TransferController.php` | Transfer endpoint | PHP |
| `app/Http/Requests/CreateTransferRequest.php` | Transfer validation rules | PHP |
| `app/Events/UserCreated.php` | Domain event fired on user creation | PHP |
| `app/Listeners/CreateUserWallet.php` | Creates default BRA wallet for new users | PHP |
| `app/Providers/EventServiceProvider.php` | Event/listener registration | PHP |
| `database/migrations/` | Users, wallets and transfers migrations | PHP |
| `database/factories/` | Model factories | PHP |
| `routes/api.php` | API routes | PHP |

## Conventions
- All money stored as `bigint` cents; use `MoneyCast` for display decimal.
- Business logic lives in service classes; controllers are thin.
- Validation uses `FormRequest` classes.
- Use backed enums for domain values.
- Prefer constructor injection and typed signatures.

## Commands
```bash
# Run migrations
docker compose run --rm app php artisan migrate

# Quality tools
docker compose run --rm app composer lint
docker compose run --rm app composer stan
docker compose run --rm app composer phpmd
docker compose run --rm app vendor/bin/phpunit
```

## Related
- Parent: ../agents.md
