# Laravel Application

## Overview
Laravel 13 sandbox application implementing a relational wallet/transfer payment domain on top of PostgreSQL, Redis, RabbitMQ and Kafka infrastructure.

## Structure
| File/Folder | Purpose | Type |
|-------------|---------|------|
| `app/Casts/` | Custom Eloquent casts (e.g. MoneyCast). | PHP |
| `app/Console/Commands/` | Artisan operational commands. | PHP |
| `app/Enums/` | Backed enums: CurrencyType, UserType, DocumentType, TransferStatus, FailureReason. | PHP |
| `app/Events/` | Domain events (UserCreated). | PHP |
| `app/Http/Controllers/Api/V1/` | API controllers: TokenController, TransferController. | PHP |
| `app/Http/Requests/` | FormRequest validation classes. | PHP |
| `app/Jobs/` | Queueable notification jobs. | PHP |
| `app/Listeners/` | Event listeners (CreateUserWallet). | PHP |
| `app/Models/` | Eloquent models: User, Wallet, Transfer, IdempotencyKey. | PHP |
| `app/Providers/` | Service providers. | PHP |
| `app/Services/` | Business-logic services including AuthorizerClient, NotificationClient, LoginService, WalletTransferService, and legacy Kafka/RabbitMQ messaging services. | PHP |
| `app/Support/` | Domain helpers (e.g. MoneyParser). | PHP |
| `bootstrap/app.php` | Application bootstrap. | PHP |
| `config/` | Configuration files including services.php and sanctum.php. | PHP |
| `database/factories/` | Model factories. | PHP |
| `database/migrations/` | Database migrations. | PHP |
| `routes/api.php` | API routes. | PHP |
| `tests/` | PHPUnit unit and feature tests. | Directory |

## Conventions
- All money stored as `bigint` cents; never use float for money.
- API money input is accepted as decimal strings and converted to integer cents via `App\Support\MoneyParser::parseToCents()`. `MoneyCast` is strict `int`/`int`; assignments must be integer cents.
- Business logic lives in service classes; controllers are thin.
- Validation uses `FormRequest` classes.
- Use backed enums for domain values.
- Prefer constructor injection and typed signatures.
- External HTTP clients (AuthorizerClient, NotificationClient) use `Http::timeout()` and do not throw on 4xx/5xx.
- AuthorizerClient retries only on `ConnectionException` with a single exponential backoff.

## Commands
```bash
# Run migrations
docker compose run --rm app php artisan migrate

# Quality tools
docker compose run --rm app composer lint
docker compose run --rm app composer stan
docker compose run --rm app composer phpmd
docker compose run --rm app composer test
```

## Related
- Parent: ../agents.md
