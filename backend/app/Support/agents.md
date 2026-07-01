# Support

## Overview
Domain helpers and small utility classes used across the application.

## Files
- `MoneyParser.php` — Canonical decimal-string → integer-cents parser. `MoneyParser::parseToCents()` accepts values such as `"10"`, `"10.5"`, `"10.50"`, `"0.01"` and rejects comma decimals, more than two fraction digits, signs, scientific notation, and empty/whitespace input.
- `DatabaseErrorResponse.php` — Builds JSON error responses for database-level exceptions (connection errors, constraint violations, generic SQL errors). Called from `bootstrap/app.php` exception renderables to keep bootstrap free of business logic.

## Conventions
- `App\Support\MoneyParser` is the single source of truth for converting API money strings to integer cents.
- All money assignments to Eloquent models must be integer cents because `MoneyCast` is strict int/int.

## Related
- Parent: /app/agents.md
- Related: /app/Casts/agents.md, /app/Http/Requests/agents.md
