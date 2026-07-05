# ValueObjects

## Overview
Immutable, typed data transfer objects used to carry validated request data from the HTTP layer into services.

## Structure
| File | Purpose | Type |
|------|---------|------|
| `RegisterData.php` | Aggregates registration fields: name, email, password, user type, and optional document. | PHP |
| `DocumentData.php` | Represents a single tax/document record: country, document type, and value. | PHP |

## Conventions
- Value objects are `final readonly` classes with public promoted properties.
- They are built inside FormRequest helper methods to keep controllers concise and type-safe.
- Optional domain values use nullable properties; services apply sensible defaults when values are absent.

## Related
- Parent: ../AGENTS.md
