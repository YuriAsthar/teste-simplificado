# Exceptions

## Overview
Domain HTTP exceptions used by the transfer and idempotency flows. All are rendered as JSON by global renderables in `bootstrap/app.php` for API requests.

## Files
- `IdempotencyKeyInProgressException.php` — Returned as HTTP 409 with code `transfer_in_progress` when a request reuses an idempotency key that is currently `Processing` and not stale.
- `IdempotencyKeyFingerprintMismatchException.php` — Returned as HTTP 409 with code `idempotency_key_reuse_with_different_payload` when a key is reused with a different payload fingerprint.
- `TransientAuthorizerException.php` — Returned as HTTP 503 with code `authorizer_unavailable` when the external authorizer returns a transient error or connection failure.
- `AuthorizerRejectedException.php` — Returned as HTTP 422 with code `authorizer_rejected` when the external authorizer explicitly rejects the transfer. Extends `Symfony\Component\HttpKernel\Exception\HttpException`; processing idempotency keys are deleted so the request can be retried.

## Conventions
- Exceptions implement `Symfony\Component\HttpKernel\Exception\HttpExceptionInterface` (or extend `HttpException`) so Laravel can derive the status code.
- Controllers do not catch these exceptions; global exception rendering handles API responses.

## Related
- Parent: /app/agents.md
- Related: /app/Services/agents.md, /app/Http/Controllers/Api/V1/agents.md, /app/bootstrap/app.php
