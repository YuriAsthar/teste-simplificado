# Tests

## Overview
Test suite for the Laravel application: unit tests for isolated components and feature tests for end-to-end behavior.

## Structure
| File/Folder | Purpose | Type |
|-------------|---------|------|
| `Feature/` | End-to-end HTTP, console, and integration tests. | Directory |
| `Unit/` | Isolated unit tests for services, jobs, casts, and helpers. | Directory |
| `TestCase.php` | Base test case shared across the suite. | PHP |

## Conventions
- Feature tests use `LazilyRefreshDatabase`.
- Unit tests use Mockery and clean up in `tearDown()`.
- `tests/Unit/Support/` contains `MoneyParserTest.php`.
- External HTTP calls are faked with `Http::fake()`.
- PHPStan baseline contains intentional ignores for Mockery mock assignments in tests.

## Commands
```bash
docker compose run --rm app composer test
docker compose run --rm app ./vendor/bin/phpunit --filter=AuthorizerClientTest
```

## Related
- Parent: /backend/agents.md
- Children: /backend/tests/Feature/agents.md, /backend/tests/Unit/agents.md
