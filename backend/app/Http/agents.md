# Http

## Overview
HTTP layer for the API-only wallet application: controllers, FormRequests, and API resources.

## Structure
| File/Folder | Purpose | Type |
|-------------|---------|------|
| `Controllers/Api/V1/` | API controllers (Register, Token, Transfer, Logout). | PHP |
| `Requests/` | FormRequest validation classes. | PHP |
| `Resources/` | API response resources (e.g. RegisterResponseResource). | PHP |

## Conventions
- Controllers are thin: validation, authorization, and response formatting only.
- Business logic is delegated to service classes in `app/Services/`.
- FormRequest classes provide typed helper methods (e.g. `registerData()`) that build value objects.
- API resources format model data and related metadata (tokens, relationships) for JSON responses.

## Related
- Parent: ../agents.md
