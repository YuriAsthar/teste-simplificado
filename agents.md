# Docker Laravel Infrastructure

## Overview
Complete Docker environment for Laravel 13 with PostgreSQL, Redis, RabbitMQ, Kafka, and Nginx.

## Structure
| File/Folder | Purpose | Type |
|-------------|---------|------|
| `/app` | Laravel application code | Directory |
| `/docker` | Docker configurations | Directory |
| `/docker/nginx/default.conf` | Nginx server configuration | Config |
| `/docker/init-multi-db.sql` | Database initialization script | SQL |
| `/Dockerfile` | PHP 8.4-FPM container definition | Docker |
| `/docker-compose.yml` | Multi-service orchestration | Docker Compose |
| `/app/.env.example` | Sandbox environment template | Config |
| `/app/.env.testing` | Testing environment config | Config |
| `/app/ecs.php` | EasyCodingStandard configuration | Config |
| `/app/phpstan.neon` | PHPStan analysis configuration | Config |
| `/app/phpstan-baseline.neon` | PHPStan baseline rules | Config |
| `/app/rector.php` | Rector refactoring configuration | Config |

## Services
- **app**: PHP 8.4-FPM with Laravel 13
- **web**: Nginx reverse proxy (port 8080)
- **db**: PostgreSQL 16 (port 6432)
- **redis**: Redis 7 (port 7379)
- **rabbitmq**: RabbitMQ 3 Management (ports 6672, 16672)
- **zookeeper**: Zookeeper (port 2181)
- **kafka**: Kafka broker (port 10092)

## Conventions
- All Docker commands use `docker compose run --rm` (never exec)
- Laravel runs as www-data user
- Database migrations use healthcheck dependency
- Alternative ports used to avoid conflicts (64xx, 73xx, 66xx, 166xx, 10092)

## Commands
```bash
# Start stack
docker compose up -d

# Run artisan commands
docker compose run --rm app php artisan <command>

# Run migrations
docker compose run --rm app php artisan migrate
docker compose run --rm app php artisan migrate --env=testing

# Quality tools (run inside app container)
docker compose run --rm app composer lint
docker compose run --rm app composer lint-fix
docker compose run --rm app composer stan
docker compose run --rm app composer rector
docker compose run --rm app composer phpmd
docker compose run --rm app composer test
```

## Related
- Parent: ../agents.md
