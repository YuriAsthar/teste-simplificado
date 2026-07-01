# Laravel Application

## Overview
Laravel 13 API-only wallet/transfer application. It exposes a JSON API for authentication and money transfers on top of PostgreSQL, Redis, RabbitMQ and Kafka infrastructure. There is no Blade/web frontend and all public API endpoints are under `/api/v1`.

## Structure
| File/Folder | Purpose | Type |
|-------------|---------|------|
| `app/Casts/` | Custom Eloquent casts (e.g. MoneyCast). | PHP |
| `app/Console/Commands/` | Artisan operational commands, including `outbox:publish`. | PHP |
| `app/Enums/` | Backed enums: CurrencyType, UserType, DocumentType (Stripe-standard tax IDs), TransferStatus, FailureReason, IdempotencyKeyStatus, AuthorizerResult, OutboxStatus. | PHP |
| `app/Events/` | Domain events (UserCreated). | PHP |
| `app/Exceptions/` | Domain HTTP exceptions: `IdempotencyKeyInProgressException`, `IdempotencyKeyFingerprintMismatchException`, `TransientAuthorizerException`, `NotificationException`. | PHP |
| `app/Http/Controllers/Api/V1/` | API controllers: RegisterController, TokenController, TransferController, LogoutController. | PHP |
| `app/Http/Requests/` | FormRequest validation classes. | PHP |
| `app/Http/Resources/` | API response resources (e.g. RegisterResponseResource). | PHP |
| `app/Rules/` | Custom `ValidationRule` implementations (e.g. CpfRule, CnpjRule, ValidateEmail). | PHP |
| `app/ValueObjects/` | Immutable DTOs carrying validated request data into services (RegisterData, DocumentData). | PHP |
| `app/Jobs/` | Queueable notification jobs (`SendNotificationJob`). | PHP |
| `app/Listeners/` | Event listeners (CreateUserWallet). | PHP |
| `app/Models/` | Eloquent models: User, Wallet, Transfer, IdempotencyKey, OutboxEvent. | PHP |
| `app/Providers/` | Service providers. | PHP |
| `app/Services/` | Business-logic services including AuthorizerClient, NotificationService, LoginService, RegisterService, WalletTransferService, IdempotencyKeyService, OutboxPublisher, and Kafka/RabbitMQ messaging services. | PHP |
| `app/Support/` | Domain helpers (e.g. MoneyParser). | PHP |
| `config/transfer.php` | Transfer-specific configuration (idempotency processing TTL). | PHP |
| `config/outbox.php` | Outbox retry/max-attempts configuration. | PHP |
| `bootstrap/app.php` | Application bootstrap (API-only routing setup). | PHP |
| `config/` | Configuration files including services.php and sanctum.php. | PHP |
| `database/factories/` | Model factories. | PHP |
| `database/migrations/` | Database migrations. | PHP |
| `routes/api.php` | API routes (served under `/api/v1`). | PHP |
| `routes/web.php` | Minimal JSON health-check route only. | PHP |
| `tests/` | PHPUnit unit and feature tests. | Directory |

## Conventions
- All money stored as `bigint` cents; never use float for money.
- API money input is accepted as integer cents (`amount`, > 0). `MoneyCast` is strict `int`/`int`; assignments must be integer cents.
- `POST /api/v1/transfer` requires the `Idempotency-Key` header (non-empty string) and the `amount` integer field; legacy decimal `value` is rejected. The authenticated user must match the `payer`; otherwise a 403 is returned.
- Idempotency key request hash = SHA-256 of fixed-order `payer_id:payee_id:amount`; the `idempotency_keys` table stores `endpoint`, `request_hash`, `response_status`, and `response_body`. On replay with a completed key whose hash matches, the cached HTTP response (status + body) is returned.
- Transient authorizer failures (connection errors / 5xx / 503) return HTTP 503, are rendered by global exception handlers, and the processing idempotency row is removed so the client can retry. Authorizer rejection returns HTTP 422.
- Failed idempotency-guarded transfers are persisted, and replays return the persisted failed transfer.
- Completed transfers write a `transfer.completed` event into the `outbox_events` table within the same DB transaction. A scheduled `outbox:publish --batch=100` command (with `WithoutOverlapping`) polls pending events and publishes them to Kafka.
- Kafka consumers read `wallet.transfer.completed`, deduplicate via Redis `kafka:transfer:{transfer_id}`, and dispatch `SendNotificationJob` via the RabbitMQ connection. If the transfer is missing or not completed, the message is marked processed to avoid endless redelivery.
- `notified_at` on the `Transfer` model is the final idempotency guard inside `SendNotificationJob::handle()`.
- Notification failures are logged and retried through RabbitMQ; they do not break transfer completion.
- Business logic lives in service classes; controllers are thin.
- Validation uses `FormRequest` classes.
- Use backed enums for domain values.
- Prefer constructor injection and typed signatures.
- External HTTP clients (AuthorizerClient, NotificationClient) use `Http::timeout()`; AuthorizerClient returns an `AuthorizerResult` enum (`Authorized`, `Rejected`, `Transient`) instead of throwing on transient responses.
- AuthorizerClient retries only on `ConnectionException` with a single exponential backoff.
- API-only: no Blade views, web login routes, dashboard, query-string tokens, or session/cookie auth.
- Authentication is stateless via Sanctum bearer tokens: obtain a token at `POST /api/v1/auth/login`, then send `Authorization: Bearer <token>`. New accounts are created at `POST /api/v1/auth/register` (public route), which also returns a bearer token. Registration accepts an optional `type` (`common` or `merchant`) and three required document fields: `document_country` (3-letter ISO code), `document_type` (Stripe-standard tax ID code), and `document_value`. The provided document type must be valid for the given country and is validated against `DocumentType`. For Brazilian documents, `document_value` is now algorithmically validated: `br_cpf` must be a valid CPF and `br_cnpj` must be a valid CNPJ. Both formatted (e.g. `529.982.247-25`, `11.222.333/0001-81`) and unformatted values are accepted during validation; the value is stored exactly as submitted.

## Cache / QA Tool Artifacts
- The following tool caches are ignored and must not be committed:
  - PHPUnit: `.phpunit.cache/`, `.phpunit.result.cache`
  - PHPStan / Larastan: `/storage/phpstan/`, `phpstan.result.cache`
  - Rector: `/storage/rector/`
  - ECS: `/.ecs_cache/`
  - PHPMD: `.phpmd.cache`
  - Generic: `*.cache.json`, `*.result.cache`
- These are listed in `.gitignore`.

## Commands
```bash
# Run migrations
docker compose run --rm app php artisan migrate

# Operational commands
docker compose run --rm app php artisan outbox:publish --batch=100

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
- Copy `/backend/.env.example` to `/backend/.env` and run `docker compose run --rm app php artisan key:generate`.
- Fix volume ownership with `docker compose run --rm --user root app chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache`.
- All Docker commands use `docker compose run --rm` (never exec).

## Related
- Parent: ../agents.md
