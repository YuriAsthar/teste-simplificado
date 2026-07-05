# API V1 Controllers

## Overview
REST API controllers for version 1 of the payment domain.

## Files
- `RegisterController.php` — Handles `POST /api/v1/auth/register`. Validates input via `RegisterRequest`, creates the user via `RegisterService`, issues a Sanctum bearer token, and returns the user data with the access token wrapped in `RegisterResponseResource`.
- `LoginController.php` — Handles `POST /api/v1/auth/login`. Validates credentials via `LoginRequest`, delegates authentication to `LoginService`, and returns the authenticated user's `id`, `name`, `email`, plus the Sanctum bearer token wrapped in `LoginResponseResource`. Logs successful and failed login attempts without exposing passwords or tokens.
- `TransferController.php` — Handles `POST /api/v1/transfer`. Validates input via `CreateTransferRequest`, resolves the acting user, delegates execution to `WalletTransferService`, and returns the transfer result wrapped in `TransferResponseResource`. Domain exceptions (`IdempotencyKeyInProgressException`, `IdempotencyPayloadMismatchException`, `TransientAuthorizerException`) are rendered globally by `bootstrap/app.php`.
- `LogoutController.php` — Handles `POST /api/v1/auth/logout`. Revokes the current Sanctum bearer token for the authenticated user and returns a plain success message.

## Conventions
- Controllers are thin: validation, authorization, and response formatting only.
- Business logic belongs in services.
- Use typed constructor injection and `JsonResponse` returns.
- This is an API-only surface: no Blade responses, session flash, or cookie-based auth.
- Controllers do not extend a base Controller class; they are plain invokable classes.

## Related
- Parent: ./AGENTS.md
- Related: ./app/Http/Requests/AGENTS.md, ./app/Services/AGENTS.md
