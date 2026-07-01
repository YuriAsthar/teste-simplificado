# API V1 Controllers

## Overview
REST API controllers for version 1 of the payment domain.

## Files
- `TransferController.php` — Handles `POST /api/v1/transfer`. Validates input via `CreateTransferRequest`, resolves the acting user, and delegates execution to `WalletTransferService`.
- `TokenController.php` — Handles `POST /api/v1/auth/token`. Validates credentials via `LoginRequest` and returns an access token.

## Conventions
- Controllers are thin: validation, authorization, and response formatting only.
- Business logic belongs in services.
- Use typed constructor injection and `JsonResponse` returns.
- This is an API-only surface: no Blade responses, session flash, or cookie-based auth.

## Related
- Parent: /app/agents.md
- Related: /app/Http/Requests/agents.md, /app/Services/agents.md
