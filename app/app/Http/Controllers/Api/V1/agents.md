# API V1 Controllers

## Overview
REST API controllers for version 1 of the payment domain.

## Files
- `TransferController.php` — Handles `POST /api/transfer`. Validates input via `CreateTransferRequest`, resolves the acting user, and delegates execution to `WalletTransferService`.
- `TokenController.php` — Handles `POST /api/auth/token`. Validates credentials via `LoginRequest` and returns an access token.

## Conventions
- Controllers are thin: validation, authorization, and response formatting only.
- Business logic belongs in services.
- Use typed constructor injection and `JsonResponse` returns.

## Related
- Parent: /app/agents.md
- Related: /app/Http/Requests/agents.md, /app/Services/agents.md
