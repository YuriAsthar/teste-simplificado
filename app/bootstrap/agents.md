# Bootstrap

## Overview
Application bootstrap for the Laravel 13 API-only wallet/transfer application.

## Files
- `app.php` — Configures the foundation application with API-only routing (`web.php` for health check only, `api.php` under `/api/v1`, `console.php` for scheduled/console commands) and registers the `app/Console/Commands` directory.
- `providers/` — Service providers loaded by the framework.

## Conventions
- Exception rendering is JSON-only for `api/*` requests.
- Domain exceptions (`IdempotencyKeyInProgressException`, `IdempotencyKeyFingerprintMismatchException`, `TransientAuthorizerException`) are registered as `renderable` callbacks with stable `code` strings and appropriate HTTP status codes.
- No web/Blade frontend is wired into the bootstrap.

## Related
- Parent: /app/agents.md
- Related: /app/app/Http/Controllers/Api/V1/agents.md, /app/routes/agents.md
