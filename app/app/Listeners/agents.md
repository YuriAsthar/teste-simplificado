# Listeners

## Overview
Event listeners that handle domain side effects decoupled from model persistence.

## Files
- `CreateUserWallet.php` — Creates a default `BRA` wallet with zero balance when a user is created.

## Conventions
- Listeners are thin; heavy logic should delegate to services.
- Registration happens in `App\Providers\EventServiceProvider`.

## Related
- Parent: /app/agents.md
- Sibling: /app/Events/agents.md
- Related: /app/Providers/agents.md
