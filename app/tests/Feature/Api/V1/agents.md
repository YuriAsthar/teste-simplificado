# Feature Tests: API V1

## Overview
End-to-end tests for version 1 API endpoints.

## Files
- `TransferControllerTest.php` — Covers successful transfers, idempotency replay, 409 fingerprint mismatch, 409 in-progress, missing/empty `Idempotency-Key` header, `amount` validation, transient authorizer 503 + retry, failed-transfer replay, missing-payer replay, and business-rule failures.
- `TokenControllerTest.php` — Tests token issuance endpoint.

## Conventions
- Feature tests use `LazilyRefreshDatabase`.
- Each endpoint covers success, validation failure, and business-rule failure paths.
- Prefer `assertJsonPath` and `assertJsonValidationErrors` for precise assertions.

## Related
- Parent: /app/tests/agents.md
- Related: /app/Http/Controllers/Api/V1/agents.md, /app/Services/agents.md
