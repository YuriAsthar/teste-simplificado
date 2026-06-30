# Feature Tests: API V1

## Overview
End-to-end tests for version 1 API endpoints.

## Files
- `TransferControllerTest.php` — Covers successful transfers, idempotency, insufficient funds, currency mismatch, validation errors for missing/invalid/same wallets.

## Conventions
- Feature tests use `LazilyRefreshDatabase`.
- Each endpoint covers success, validation failure, and business-rule failure paths.
- Prefer `assertJsonPath` and `assertJsonValidationErrors` for precise assertions.

## Related
- Parent: /app/tests/agents.md
- Related: /app/Http/Controllers/Api/V1/agents.md, /app/Services/agents.md
