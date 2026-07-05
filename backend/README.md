# Wallet API

Laravel 13 API-only digital wallet with transfers between users. Endpoints for authentication and transfers, running on PostgreSQL, Redis, RabbitMQ, and Kafka.

## Documentation

- [CLI Commands](docs/clis.md)
- [Queue & Jobs](docs/queue.md)
- [API Endpoints](docs/api-requests.md)
- [Kafka Usage](docs/kafka.md)

## Quick Start

```bash
# Start containers
docker compose up -d

# Run migrations
docker compose run --rm app php artisan migrate --force

# Run tests
docker compose run --rm app composer test
```

## Common Commands

### Code Quality

```bash
# Lint code
docker compose run --rm app composer lint

# Auto-fix linting issues
docker compose run --rm app composer lint-fix

# Run static analysis
docker compose run --rm app composer stan

# Run Rector refactoring
docker compose run --rm app composer rector

# Run PHPMD
docker compose run --rm app composer phpmd
```

### Testing

```bash
# Run full test suite
docker compose run --rm app composer test

# Run specific test
docker compose run --rm app ./vendor/bin/phpunit --filter=WalletTransferServiceTest
```

### Scheduler

```bash
# Run scheduled tasks manually
docker compose run --rm app php artisan schedule:run
```

### Database Access

```bash
# Connect to PostgreSQL
docker compose run --rm db psql -U wallet_user -d wallet_sandbox
```

## Services

| Service | Port | Description |
|---------|------|-------------|
| app | 9000 | PHP 8.4-FPM |
| web | 8080 | Nginx |
| db | 6432 | PostgreSQL 16 |
| redis | 7379 | Redis 7 |
| rabbitmq | 6672, 16672 | RabbitMQ 3 Management |
| kafka | 10092 | Kafka broker |

## Environment

Copy `.env.example` to `.env` and configure:
- `NGINX_HOST_PORT`: Nginx host port (default: 8080)
- `DB_DATABASE`: PostgreSQL database name (`wallet_sandbox`)

Generate application key:

```bash
docker compose run --rm app php artisan key:generate
```