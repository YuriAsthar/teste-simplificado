# Feature Tests: API V1

## Overview
End-to-end tests for version 1 API endpoints.

## Files
- `TransferControllerTest.php` — Covers successful transfers, idempotency replay, 409 fingerprint mismatch, 409 in-progress, missing/empty `Idempotency-Key` header, `amount` validation, transient authorizer 503 + retry, authorizer rejection 422 + retry, failed-transfer replay, missing-payer replay, auto-population of `payer` from the authenticated user, and business-rule failures.
- `RegisterControllerTest.php` — Tests `POST /api/v1/auth/register` user registration endpoint, including successful registration with bearer token issuance and validation failures. Asserts that duplicate-email and malformed-email requests return the same generic English `email` error message (`Invalid email`) to prevent user enumeration.
- `TokenControllerTest.php` — Tests `POST /api/v1/auth/login` token issuance endpoint, including user fields in the success response.

## Conventions
- Feature tests use `LazilyRefreshDatabase`.
- Each endpoint covers success, validation failure, and business-rule failure paths.
- Prefer `assertJsonPath` and `assertJsonValidationErrors` for precise assertions.

## Related
- Parent: ./tests/agents.md
- Related: ./app/Http/Controllers/Api/V1/agents.md, ./app/Services/agents.md
