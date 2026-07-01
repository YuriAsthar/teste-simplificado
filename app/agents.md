# Laravel Application

## Overview
Laravel 13 API-only wallet/transfer application. It exposes a JSON API for authentication and money transfers on top of PostgreSQL, Redis, RabbitMQ and Kafka infrastructure. There is no Blade/web frontend and all public API endpoints are under `/api/v1`.

## Structure
| File/Folder | Purpose | Type |
|-------------|---------|------|
| `app/Casts/` | Custom Eloquent casts (e.g. MoneyCast). | PHP |
| `app/Console/Commands/` | Artisan operational commands. | PHP |
| `app/Enums/` | Backed enums: CurrencyType, UserType, DocumentType (Stripe-standard tax IDs), TransferStatus, FailureReason. | PHP |
| `app/Events/` | Domain events (UserCreated). | PHP |
| `app/Http/Controllers/Api/V1/` | API controllers: TokenController, TransferController. | PHP |
| `app/Http/Requests/` | FormRequest validation classes. | PHP |
| `app/Jobs/` | Queueable notification jobs. | PHP |
| `app/Listeners/` | Event listeners (CreateUserWallet). | PHP |
| `app/Models/` | Eloquent models: User, Wallet, Transfer, IdempotencyKey. | PHP |
| `app/Providers/` | Service providers. | PHP |
| `app/Services/` | Business-logic services including AuthorizerClient, NotificationClient, LoginService, WalletTransferService, and legacy Kafka/RabbitMQ messaging services. | PHP |
| `app/Support/` | Domain helpers (e.g. MoneyParser). | PHP |
| `bootstrap/app.php` | Application bootstrap (API-only routing setup). | PHP |
| `config/` | Configuration files including services.php and sanctum.php. | PHP |
| `database/factories/` | Model factories. | PHP |
| `database/migrations/` | Database migrations. | PHP |
| `routes/api.php` | API routes (served under `/api/v1`). | PHP |
| `routes/web.php` | Minimal JSON health-check route only. | PHP |
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
- API-only: no Blade views, web login routes, dashboard, query-string tokens, or session/cookie auth.
- Authentication is stateless via Sanctum bearer tokens: obtain a token at `POST /api/v1/auth/token`, then send `Authorization: Bearer <token>`.

## Commands
```bash
# Run migrations
docker compose run --rm app php artisan migrate

# Quality tools
docker compose run --rm app composer lint
docker compose run --rm app composer lint-fix
docker compose run --rm app composer stan
docker compose run --rm app composer rector
docker compose run --rm app composer phpmd
docker compose run --rm app composer test
```

## Setup
- Copy `/.env.example` to `/.env` to configure the Nginx host port (`NGINX_HOST_PORT`, default 8080).
- Copy `/app/.env.example` to `/app/.env` and run `docker compose run --rm app php artisan key:generate`.
- Fix volume ownership with `docker compose run --rm --user root app chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache`.
- All Docker commands use `docker compose run --rm` (never exec).

## Related
- Parent: ../agents.md
