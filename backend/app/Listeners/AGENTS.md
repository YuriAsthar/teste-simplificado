# Listeners

## Overview
Event listeners that handle domain side effects decoupled from model persistence.

## Files
- `CreateUserWallet.php` — Creates a default `BRA` wallet with zero balance when a user is created.

## Conventions
- Listeners are thin; heavy logic should delegate to services.
- Registration happens in `App\Providers\EventServiceProvider`.

## Related
- Parent: /app/AGENTS.md
- Sibling: /app/Events/AGENTS.md
- Related: /app/Providers/AGENTS.md
