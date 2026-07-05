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
- Parent: /app/AGENTS.md
- Related: /app/Events/AGENTS.md, /app/Listeners/AGENTS.md, /app/bootstrap/AGENTS.md
