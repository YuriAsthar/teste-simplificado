# Wallet API

<p align="center">
  <img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="300" alt="Laravel Logo">
</p>

Laravel 13 API-only wallet/transfer application. It exposes a JSON API for authentication and money transfers on top of PostgreSQL, Redis, RabbitMQ and Kafka infrastructure.

## API surface

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/api/v1/auth/login` | Issue a Sanctum bearer token from email/password. |
| POST | `/api/v1/auth/register` | Create a new user account and issue a Sanctum bearer token (public). |
| POST | `/api/v1/transfer` | Execute a wallet-to-wallet transfer (authenticated). |
| GET | `/` | JSON health check. |
| GET | `/up` | Laravel health check endpoint. |

All endpoints return JSON. Authentication is stateless: send `Authorization: Bearer <token>` for protected routes.

## Project approach

- **API-only**: no Blade views, no web login/dashboard, no session/cookie authentication for API consumption.
- **Bearer tokens**: `laravel/sanctum` issues personal access tokens via `POST /api/v1/auth/login`.
- **Business logic in services**: controllers are thin and delegate to `LoginService` and `WalletTransferService`.
- **Money as integer cents**: API values are decimal strings; they are parsed to integer cents before persistence.
- **Asynchronous notifications**: transfer completion dispatches `SendTransferNotificationJob` via RabbitMQ. External notification calls use `util.devi.tools/api/v1/notify`.

## Local setup

1. Copy root `.env.example` to `.env` (configures `NGINX_HOST_PORT`, default `8080`).
2. Copy `app/.env.example` to `app/.env` and generate an app key:
   ```bash
   docker compose run --rm app php artisan key:generate
   ```
3. Start the stack:
   ```bash
   docker compose up -d --build
   ```
4. Fix volume ownership:
   ```bash
   docker compose run --rm --user root app chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache
   ```
5. Run migrations:
   ```bash
   docker compose run --rm app php artisan migrate --force
   ```

## Development commands

All commands run through `docker compose run --rm app` (never `docker compose exec`):

```bash
# Start development server + queue worker + log tail
docker compose run --rm app composer dev

# Run migrations
docker compose run --rm app php artisan migrate

# Run tests
docker compose run --rm app composer test

# Code quality
docker compose run --rm app composer lint
docker compose run --rm app composer lint-fix
docker compose run --rm app composer stan
docker compose run --rm app composer rector
docker compose run --rm app composer phpmd
```

## CI / GitHub Actions

Quality tools run natively on the GitHub Actions runner using `shivammathur/setup-php` and `ramsey/composer-install`, calling the same `composer <script>` commands. No Node/Vite build is required for this API-only project.

## Stack

- PHP 8.4 + Laravel 13
- PostgreSQL 16
- Redis 7
- RabbitMQ 3 Management
- Kafka + Zookeeper
- Nginx reverse proxy

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
