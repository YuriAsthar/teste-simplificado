# Casts

## Overview
Value-object and primitive transformers for Eloquent models.

## Files
- `MoneyCast.php` — strict `int`-in / `int`-out cast; rejects null, float, string, bool, array, object.

## Conventions
- Casts live in `app/Casts/` and implement `Illuminate\Contracts\Database\Eloquent\CastsAttributes`.
- Money is always persisted as integer cents; decimal formatting is a presentation concern.
- Prefer strict typing and explicit exceptions over silent coercion.

## Related
- Parent: /app/AGENTS.md
