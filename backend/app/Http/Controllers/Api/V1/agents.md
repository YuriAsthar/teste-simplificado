# API V1 Controllers

## Overview
REST API controllers for version 1 of the payment domain.

## Files
- `RegisterController.php` — Handles `POST /api/v1/auth/register`. Validates input via `RegisterRequest`, creates the user via `RegisterService`, issues a Sanctum bearer token, and returns the user data with the access token.
- `TransferController.php` — Handles `POST /api/v1/transfer`. Validates input via `CreateTransferRequest`, resolves the acting user, delegates execution to `WalletTransferService`, and formats the response. Domain exceptions (`IdempotencyKeyInProgressException`, `IdempotencyKeyFingerprintMismatchException`, `TransientAuthorizerException`) are rendered globally by `bootstrap/app.php`.
- `TokenController.php` — Handles `POST /api/v1/auth/login`. Validates credentials via `LoginRequest`, delegates authentication to `LoginService`, and returns the authenticated user's `id`, `name`, `email`, plus the Sanctum bearer token.
- `LogoutController.php` — Handles `POST /api/v1/auth/logout`. Revokes the current Sanctum bearer token for the authenticated user.

## Conventions
- Controllers are thin: validation, authorization, and response formatting only.
- Business logic belongs in services.
- Use typed constructor injection and `JsonResponse` returns.
- This is an API-only surface: no Blade responses, session flash, or cookie-based auth.

## Related
- Parent: /backend/agents.md
- Related: /backend/app/Http/Requests/agents.md, /backend/app/Services/agents.md
