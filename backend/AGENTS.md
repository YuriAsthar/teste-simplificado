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
| `./.env.example` | Compose host port template and Laravel environment template; `LOG_CHANNEL=stdout` so container logs go to Docker stdout. Kafka defaults live in `config/kafka.php`. **Note:** `KAFKA_TOPIC_COMPLETED_DELAY` was removed because `ConsumeTransfersCommand` no longer uses a sleep-based daemon loop. | Config |
| `./.env.testing` | Testing environment config; `LOG_CHANNEL=stdout`. Overrides `KAFKA_BROKERS` for host-side test runners. **Note:** `KAFKA_TOPIC_COMPLETED_DELAY` and `KAFKA_CONSUMER_GROUP_ID_RETRY` were removed. | Config |
| `./ecs.php` | EasyCodingStandard configuration | Config |
| `./phpstan.neon` | PHPStan analysis configuration | Config |
| `./phpstan-baseline.neon` | PHPStan baseline rules | Config |
| `./rector.php` | Rector refactoring configuration | Config |
| `./phpmd.xml` | PHPMD ruleset and exclusions (BooleanArgumentFlag and ElseExpression excluded for dry-run bool parameters) | Config |
| `./AGENTS.md` | Laravel application documentation | Doc |

## Services
- **app**: PHP 8.4-FPM with Laravel 13
- **db**: PostgreSQL 16 (port 6432)
- **redis**: Redis 7 (port 7379)
- **rabbitmq**: RabbitMQ 3 Management (ports 6672, 16672)
- **kafka**: Kafka broker (port 10092)

## Conventions
- All Docker commands use `docker compose run --rm` (never exec)
- Laravel runs as www-data user
- Database migrations use healthcheck dependency
- Alternative ports used to avoid conflicts (64xx, 73xx, 66xx, 166xx, 10092)

## Clean Code Conventions

### Container Logging
- Default log channel is `stdout` (`config/logging.php`) using Monolog `StreamHandler` on `php://stdout`, so `docker compose logs app` captures structured application logs.
- Keep `LOG_STACK=stdout` in `.env.example`, `.env.testing`, and `.env.ci` to preserve stdout output when the stack channel is used.

### No Inline Comments
- Code must be self-explanatory through naming and structure.
- Do NOT use `//` inline comments to explain what code does.
- Docblocks (`/** */`) are allowed for API documentation only.
- If logic needs explanation, refactor the code (rename variables, extract methods) rather than adding comments.

### Logging Imports
- Always add `use Illuminate\Support\Facades\Log;` when calling `Log::` in namespaced classes.
- Never rely on unqualified `Log` inside a namespace (e.g. `App\Services`): PHP resolves it as `App\Services\Log`, which does not exist.

### PHPMD
- Do not suppress PHPMD warnings with `@SuppressWarnings` annotations; address the root cause instead.
- If a class name triggers `LongClassName`, shorten the name rather than suppressing the rule.

### Eloquent Model Attributes
- Prefer `protected $fillable` and `protected $hidden` over Laravel 13 `#[Fillable]` / `#[Hidden]` attributes for consistency across the codebase.

## Setup
1. Copy `.env.example` to `.env` and configure the Laravel sandbox environment.
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
- Self: ./AGENTS.md
- Parent: ../AGENTS.md
