# Casts

## Overview
Value-object and primitive transformers for Eloquent models.

## Files
- `MoneyCast.php` — Converts integer-cents storage (`bigint`) to decimal string representation and back. Supports `bcmath` for precision and falls back to float rounding only when the extension is unavailable.

## Conventions
- Casts live in `app/Casts/` and implement `Illuminate\Contracts\Database\Eloquent\CastsAttributes`.
- Money is always persisted as integer cents; decimal formatting is a presentation concern.
- Prefer strict typing and explicit exceptions over silent coercion.

## Related
- Parent: /app/agents.md
