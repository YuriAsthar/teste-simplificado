# Bootstrap

## Overview
Application bootstrap for the Laravel 13 API-only wallet/transfer application.

## Files
- `app.php` — Configures the foundation application with API-only routing (`web.php` for health check only, `api.php` under `/api/v1`, `console.php` for scheduled/console commands) and registers the `app/Console/Commands` directory.
- `providers/` — Service providers loaded by the framework.

## Conventions
- Exception rendering is JSON-only for `api/*` requests.
- Domain exceptions (`IdempotencyKeyInProgressException`, `IdempotencyKeyFingerprintMismatchException`, `TransientAuthorizerException`, `AuthorizerRejectedException`) are registered as `renderable` callbacks with stable `code` strings and appropriate HTTP status codes.
- Authentication exceptions (`Illuminate\Auth\AuthenticationException`) are rendered as JSON `401` for `api/*` and JSON-expecting requests as a safety net.
- Guest redirects are intercepted in `withMiddleware` so that `api/*` or JSON requests receive a JSON `401` response instead of being redirected to a non-existent `login` route.
- No web/Blade frontend is wired into the bootstrap.

## Related
- Parent: ../agents.md
- Related: ../app/Http/Controllers/Api/V1/agents.md, ../routes/agents.md
