# Providers

## Overview
Application service providers for dependency injection and event registration.

## Files
- `AppServiceProvider.php` — General application bindings. Binds `TransferPublisherInterface` to `KafkaTransferPublisher`.
- `EventServiceProvider.php` — Maps `UserCreated` event to the `CreateUserWallet` listener.

## Conventions
- Event/listener registration is explicit in `$listen` arrays.
- Providers are registered in `bootstrap/providers.php`.

## Related
- Parent: /app/agents.md
- Related: /app/Events/agents.md, /app/Listeners/agents.md, /app/bootstrap/agents.md
