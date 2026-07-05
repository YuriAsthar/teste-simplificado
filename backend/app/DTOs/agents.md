# DTOs

## Overview
Immutable value objects for messaging and service boundaries. These classes encapsulate typed data that flows between services, replacing untyped array parameters and return types.

## Structure
| File | Purpose | Type |
|------|---------|------|
| `TransferMessagePayload.php` | Immutable DTO for Kafka transfer message payloads with `fromArray()` named constructor and `toArray()` serialization | Class (readonly) |

## Conventions
- All DTOs are `final readonly` classes — immutable value objects
- Named constructor `fromArray(array $payload): self` for construction from raw arrays
- `toArray(): array` method for serialization back to Kafka messages
- Validation happens in `fromArray()` — throws `InvalidArgumentException` for invalid data
- Nullable properties use `?Type` notation (e.g. `?int`, `?string`)
- PHPDoc `@param array<string, mixed>` on array parameters for PHPStan compliance

## Related
- Parent: ../agents.md
- Consumer: ../app/Services/agents.md