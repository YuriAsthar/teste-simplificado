# Configuration

## Overview
Laravel configuration files and application-specific config.

## Files
- `transfer.php` — Transfer-specific configuration. Currently exposes `idempotency_processing_ttl_seconds` (default 300 seconds), used by `IdempotencyKeyService` and the `idempotency:cleanup-stale-keys` command to recover or delete `Processing` idempotency keys stuck beyond the TTL.

## Conventions
- Use `env()` with sensible defaults.
- Keep domain-specific config grouped in dedicated files.

## Related
- Parent: /app/AGENTS.md
- Related: /app/Services/AGENTS.md, /app/Console/Commands/AGENTS.md
