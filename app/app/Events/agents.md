# Events

## Overview
Lightweight domain events fired by models to trigger side effects.

## Files
- `UserCreated.php` — Dispatched when a new `User` is persisted; signals that a default wallet should be created.

## Conventions
- Events are plain data objects with typed public properties.
- Model lifecycle hooks in `app/Models/` fire events; listeners in `app/Listeners/` react to them.

## Related
- Parent: /app/agents.md
- Sibling: /app/Listeners/agents.md
