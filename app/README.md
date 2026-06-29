<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework.

In addition, [Laracasts](https://laracasts.com) contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

You can also watch bite-sized lessons with real-world projects on [Laravel Learn](https://laravel.com/learn), where you will be guided through building a Laravel application from scratch while learning PHP fundamentals.

## Agentic Development

Laravel's predictable structure and conventions make it ideal for AI coding agents like Claude Code, Cursor, and GitHub Copilot. Install [Laravel Boost](https://laravel.com/docs/ai) to supercharge your AI workflow:

```bash
composer require laravel/boost --dev

php artisan boost:install
```

Boost provides your agent 15+ tools and skills that help agents build Laravel applications while following best practices.

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## Code Quality

This project uses a set of static analysis and linting tools to keep the code healthy.

### Environment distinction

- **Local development:** quality tools run inside the `app` Docker container. From the project root, run:
  ```bash
  docker compose run --rm app composer <script>
  ```
- **CI (GitHub Actions):** quality tools run natively on the runner using `shivammathur/setup-php` and `ramsey/composer-install`, calling the same `composer <script>` commands. This is the standard approach for Laravel projects.

### Available scripts

| Script | Command | Purpose |
|--------|---------|---------|
| `lint` | `composer lint` | EasyCodingStandard lint check |
| `stan` | `composer stan` | PHPStan static analysis |
| `rector` | `composer rector` | Rector code refactoring |
| `test` | `composer test` | PHPUnit test suite |
| `phpmd` | `composer phpmd` | PHPMD mess detection |

### PHPMD

[PHPMD](https://phpmd.org/) scans the codebase for common code smells.

**Configuration file:** `phpmd.xml` in the `app/` directory.

**Enabled rule sets:** `cleancode`, `codesize`, `controversial`, `design`, `naming`, `unusedcode`.

**Run inside the `app` container (local development):**

```bash
docker compose run --rm app composer phpmd
```

**Run locally from the host (CI uses this approach):**

```bash
cd app
composer phpmd
```

The `composer phpmd` script runs the locally installed `vendor/bin/phpmd` binary:

```bash
php -d error_reporting=22527 vendor/bin/phpmd app text phpmd.xml
```

- **Source:** `app` (the Laravel application source directory when run from `app/`).
- **Config:** `phpmd.xml` (includes the rule sets above).

**CI behavior:** PHPMD runs automatically as a blocking step on every push and pull request via `.github/workflows/ci.yml`.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
