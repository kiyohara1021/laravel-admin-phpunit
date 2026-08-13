# Laravel Admin Panel × PHPUnit

[![CI](https://github.com/kiyohara1021/laravel-admin-phpunit/actions/workflows/ci.yml/badge.svg)](https://github.com/kiyohara1021/laravel-admin-phpunit/actions/workflows/ci.yml)
[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg)](LICENSE)

[日本語](README.md) | [English](README.en.md)

A production-minded Laravel 13 admin panel sample whose behavior is specified by PHPUnit feature and unit tests. It demonstrates authentication, layered authorization, notice CRUD, scheduled publishing, soft deletion, filtering, and a public read-only surface.

This repository is intended for learning and reference, not direct production deployment.

## Highlights

- Administrator login and logout with brute-force throttling
- Dashboard counts by publication state
- Notice creation, editing, scheduled publishing, soft deletion, restoration, and permanent deletion
- Author attribution and null-safe ownership history
- Keyword and state filters with escaped SQL `LIKE` wildcards
- Public list and detail pages that never reveal draft, scheduled, or deleted records
- Layered authorization using middleware, policies, and gates
- 185 PHPUnit tests with 606 assertions
- CI on PHP 8.4 and 8.5, Pint, dependency review, and Larastan level 7

## Quick start

PHP 8.4 or later and Composer are required.

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
| Regular user (receives 403) | `user@example.com` | `password` |

These credentials are demonstration fixtures. Never use them in production.

## Quality checks

```bash
composer check
composer test
composer analyse
composer lint
composer security
```

Tests use an in-memory SQLite database and recreate the schema with `RefreshDatabase`. See the [Japanese README](README.md) for the complete explanation of the domain rules and test matrix.

## Design notes

- `EnsureUserIsAdmin` controls entry to the admin area.
- `NoticePolicy` controls actions on notice resources.
- `Notice::published()` is the mandatory starting scope for public queries.
- `NoticeFilter` treats malformed GET filters as display state, not exceptional commands.
- Search escapes `%`, `_`, and the escape character itself before executing a parameterized query.

Read the [architecture overview](docs/architecture.md) for the request flow and security boundaries.

## Project documents

- [Architecture](docs/architecture.md)
- [Changelog](CHANGELOG.md)
- [Contributing](CONTRIBUTING.md)
- [Code of Conduct](CODE_OF_CONDUCT.md)
- [Security policy](SECURITY.md)
- [Support policy](SUPPORT.md)

## License

Licensed under the [MIT License](LICENSE). Dependencies installed by Composer retain their respective licenses.
