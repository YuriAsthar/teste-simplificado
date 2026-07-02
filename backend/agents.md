# Docker Laravel Infrastructure

## Overview
Complete Docker environment for Laravel 13 with PostgreSQL, Redis, RabbitMQ, Kafka, and Nginx.

## Structure
| File/Folder | Purpose | Type |
|-------------|---------|------|
| `.` | Laravel application code | Directory |
| `./docker` | Docker configurations | Directory |
| `./docker/nginx/default.conf` | Nginx server configuration | Config |
| `./docker/init-multi-db.sql` | Database initialization script | SQL |
| `./Dockerfile` | PHP 8.4-FPM container definition with PHP-FPM healthcheck support; installs `pdo_pgsql`, `pgsql`, `zip`, and `sockets` PHP extensions | Docker |
| `./docker-compose.yml` | Multi-service orchestration with service_healthy dependency | Docker Compose |
| `./.env.example` | Compose host port template (NGINX_HOST_PORT) and Laravel sandbox environment template | Config |
| `./.env.testing` | Testing environment config | Config |
| `./ecs.php` | EasyCodingStandard configuration | Config |
| `./phpstan.neon` | PHPStan analysis configuration | Config |
| `./phpstan-baseline.neon` | PHPStan baseline rules | Config |
| `./rector.php` | Rector refactoring configuration | Config |
| `./phpmd.xml` | PHPMD ruleset and exclusions | Config |
| `./agents.md` | Laravel application documentation | Doc |

## Services
- **app**: PHP 8.4-FPM with Laravel 13
- **web**: Nginx reverse proxy (configurable host port via `NGINX_HOST_PORT`, default 8080)
- **db**: PostgreSQL 16 (port 6432)
- **redis**: Redis 7 (port 7379)
- **rabbitmq**: RabbitMQ 3 Management (ports 6672, 16672)
- **kafka**: Kafka broker (port 10092)

## Conventions
- All Docker commands use `docker compose run --rm` (never exec)
- Laravel runs as www-data user
- Database migrations use healthcheck dependency
- Alternative ports used to avoid conflicts (64xx, 73xx, 66xx, 166xx, 10092)

## Setup
1. Copy `.env.example` to `.env` to set `NGINX_HOST_PORT` (default 8080) and configure the Laravel sandbox environment.
2. Generate an `APP_KEY`: `docker compose run --rm app php artisan key:generate`.
3. Start stack with `docker compose up -d --build`.
4. Fix volume ownership: `docker compose run --rm --user root app chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache`.
5. Run migrations: `docker compose run --rm app php artisan migrate --force`.

## Commands
```bash
# Start stack
docker compose up -d

# Run artisan commands
docker compose run --rm app php artisan <command>

# Run migrations
docker compose run --rm app php artisan migrate
docker compose run --rm app php artisan migrate --env=testing

# Quality tools (run inside app container for local development)
docker compose run --rm app composer lint
docker compose run --rm app composer lint-fix
docker compose run --rm app composer stan
docker compose run --rm app composer rector
docker compose run --rm app composer test
docker compose run --rm app composer phpmd

# CI note: GitHub Actions runs the same composer scripts natively on the runner
# using shivammathur/setup-php + ramsey/composer-install (standard Laravel pattern).
```

## API Versioning
- The application is API-only: no Blade/web frontend, dashboard, login pages, query-string tokens, or session/cookie auth.
- All API endpoints are under `/api/v1`.
- Authentication is stateless via Sanctum bearer tokens: `POST /api/v1/auth/login` issues tokens; protected routes require `Authorization: Bearer <token>`. Account creation is public at `POST /api/v1/auth/register`, which also returns a bearer token. Registration requires three document fields: `document_country`, `document_type`, and `document_value`; `document_type` is validated against `DocumentType` and must be allowed for the provided country.

## Related
- Self: ./agents.md
- Parent: ../agents.md
