# Laravel Admin Panel × PHPUnit

[![CI](https://github.com/kiyohara1021/laravel-admin-phpunit/actions/workflows/ci.yml/badge.svg)](https://github.com/kiyohara1021/laravel-admin-phpunit/actions/workflows/ci.yml)
![Laravel 13](https://img.shields.io/badge/Laravel-13-FF2D20?logo=laravel&logoColor=white)
![PHP 8.4 / 8.5](https://img.shields.io/badge/PHP-8.4%20%7C%208.5-777BB4?logo=php&logoColor=white)
[![Latest release](https://img.shields.io/github/v/release/kiyohara1021/laravel-admin-phpunit)](https://github.com/kiyohara1021/laravel-admin-phpunit/releases)
[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg)](LICENSE)

[日本語](README.md) | [English](README.en.md)

A security-conscious Laravel 13 learning and reference implementation that shows not only how to build an admin panel, but **how to lock down fragile authentication, authorization, search, and publication boundaries with PHPUnit**.

![Notice management with published, scheduled, and draft states](docs/images/admin-notices.jpg)

<p align="center">Manage published, scheduled, and draft notices with search, state filters, and soft deletion.</p>

## Beyond a basic CRUD sample

- Never leak draft, scheduled, or deleted records through public search
- Keep middleware, policies, and form validation responsibilities separate
- Model draft, published, and scheduled states with one `published_at` field
- Treat `%`, `_`, and `!` as literal search input instead of SQL wildcards
- Throttle login attempts by normalized email address and IP
- Compare realistic Feature and Unit test boundaries

See the [architecture overview](docs/architecture.md), [domain model](docs/domain-model.md), [search design](docs/search-and-filtering.md), and [testing strategy](docs/testing.md) for the details. The detailed documents are bilingual where practical and include code identifiers that map directly to the implementation.

## Quality at a glance

| PHPUnit | Static analysis | PHP matrix |
| --- | --- | --- |
| 185 tests / 674 assertions | Larastan level 10 (test code included) | 8.4 / 8.5 |

Tests run in a randomized order, and risky tests, warnings, and deprecations all fail the build. See the [testing strategy](docs/testing.md) for the details.

CI runs PHPUnit, coverage, Pint, Larastan, Composer validation, a dependency audit, and Dependency Review. The protected `main` branch requires a pull request and passing checks.

## Quick start

PHP `^8.4` and Composer are required.

```bash
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate --seed
php artisan serve
```

Open `http://localhost:8000`.

| Role | Email | Password |
| --- | --- | --- |
| Administrator | `admin@example.com` | `password` |
| Regular user (admin routes return 403) | `user@example.com` | `password` |

These credentials are demonstration fixtures. Never use them in production.

## Screens

<table>
  <tr>
    <td><img src="docs/images/admin-dashboard.jpg" alt="Admin dashboard with counts by publication state"></td>
    <td><img src="docs/images/admin-notice-form.jpg" alt="Notice form with scheduled publication date"></td>
  </tr>
  <tr>
    <td align="center">Dashboard counts by publication state</td>
    <td align="center">Draft, immediate, and scheduled publication</td>
  </tr>
</table>

![Public notice list containing only published records](docs/images/public-notices.jpg)

<p align="center">The public surface only exposes published notices. No login or API key is required.</p>

## Behaviors under test

- Administrator authentication, logout, and brute-force throttling
- Notice CRUD, scheduled publication, soft deletion, restore, and force delete
- Layered authorization with middleware, policies, and gates
- Invalid state filters, pagination, and SQL wildcard escaping
- Prevention of draft, scheduled, and deleted record leakage
- HTML escaping and null-safe author history

See the [testing strategy](docs/testing.md) for the file-by-file matrix.

## Quality commands

```bash
composer check     # PHPUnit + Larastan + Pint
composer test      # PHPUnit
composer analyse   # Larastan level 10
composer lint      # Pint check
composer security  # Known vulnerability audit
```

## Project documents

- [Architecture](docs/architecture.md)
- [Domain model](docs/domain-model.md)
- [Search and filtering](docs/search-and-filtering.md)
- [Testing strategy](docs/testing.md)
- [Changelog](CHANGELOG.md)
- [Contributing](CONTRIBUTING.md)
- [Security policy](SECURITY.md)
- [Support policy](SUPPORT.md)

## Production boundary

This repository is for learning and reference. Production use requires removing demo credentials and adding password recovery, audit logging, backups, monitoring, and deployment-specific hardening.

## License

[MIT License](LICENSE). Composer dependencies retain their respective licenses.
