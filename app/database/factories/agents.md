# Factories

## Overview
Model factories for test data generation.

## Files
- `UserFactory.php` — Generates common users with document triple and a hashed password. Removing `email_verified_at` and `remember_token` matches the current schema.
- `WalletFactory.php` — Generates wallets with zero balance in `BRA` currency.
- `TransferFactory.php` — Generates a completed transfer between two auto-created users and their wallets.

## Conventions
- Let the model's `hashed` password cast handle hashing; factories should pass plain-text passwords where the cast is present.
- Factories are deterministic and leverage model relations for valid test data.

## Related
- Parent: /app/agents.md
- Related: /app/Models/agents.md, /app/tests/agents.md
