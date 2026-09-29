# SaaS Corp — Application Scaffold

A generic, minimal Laravel + Livewire scaffold for SaaS Corp's product. No
product-specific domain logic is baked in yet — the Product Lead is still
defining scope, so this repo intentionally stays a clean starting point:
routing, auth-ready structure, a working Livewire component, tests, linting,
and CI are set up; feature work builds on top of this.

## Stack

- PHP 8.3
- Laravel 13.x (latest stable)
- Livewire 4.x (latest stable)
- SQLite for local development (swap to MySQL/Postgres per environment via `.env`)
- Vite + Tailwind CSS 4 for the frontend build
- Pest/PHPUnit for tests, Laravel Pint for code style
- GitHub Actions CI (lint + test on every push/PR)

## Requirements

- PHP >= 8.3 with the standard Laravel extensions (`mbstring`, `dom`,
  `fileinfo`, `sqlite3`/`pdo_sqlite`, `curl`, `intl`, `zip`, `bcmath`, `gd`)
- Composer 2.x
- Node.js 20+ and npm

## Setup

```bash
composer install
cp .env.example .env
php artisan key:generate

# Local dev database (SQLite, default in .env.example)
touch database/database.sqlite
php artisan migrate

npm install
npm run build   # or `npm run dev` for the Vite dev server
```

Run the app:

```bash
php artisan serve
```

Then visit:

- `/` — default Laravel welcome page
- `/dashboard` — a minimal Livewire counter component proving the
  Livewire wiring works end-to-end (safe to delete once real product
  screens replace it)

## Environment configuration

Copy `.env.example` to `.env` and adjust as needed. Key variables:

| Variable | Purpose | Local default |
|---|---|---|
| `APP_ENV` | Environment name | `local` |
| `APP_KEY` | Encryption key, generate with `php artisan key:generate` | (generated) |
| `APP_URL` | Base URL used by generated links | `http://localhost:8000` |
| `DB_CONNECTION` | Database driver | `sqlite` |
| `DB_DATABASE` | SQLite file path (or db name for other drivers) | `database/database.sqlite` |

For non-SQLite databases, uncomment/set the `DB_*` host/port/username/password
variables in `.env`.

## Testing

```bash
./vendor/bin/phpunit
# or
php artisan test
```

## Code style

Laravel Pint enforces PHP code style (PSR-12-based):

```bash
./vendor/bin/pint          # auto-fix
./vendor/bin/pint --test   # check only, no changes (used in CI)
```

## CI

`.github/workflows/ci.yml` runs on every push/PR to `main`:

1. Install PHP + Composer dependencies
2. Install Node + npm dependencies, build assets
3. Generate app key, create SQLite DB, run migrations
4. Lint with Pint (`--test`, fails on violations)
5. Run the test suite

## Project conventions

- Keep this scaffold generic. Do not add niche-specific domain models,
  migrations, or UI until product scope is confirmed by the Product Lead
  and approved by the founder.
- New features: prefer Livewire full-page components under
  `app/Livewire` (or `resources/views/components/*.blade.php` single-file
  components) wired to routes in `routes/web.php`.
- Run `./vendor/bin/pint` and `./vendor/bin/phpunit` before opening a PR;
  CI enforces both.

## Spec-Driven Development

This repo uses [GitHub Spec Kit](https://github.com/github/spec-kit) (the
CLI implementation of [specdriven.ai](https://specdriven.ai/)'s
Spec-Driven Development methodology) to keep specs as the source of truth
for feature work. See `.specify/memory/constitution.md` for the project's
constitution (stack rules, workflow, scope boundary between Product and
Engineering).

Workflow for any non-trivial feature:

1. `/speckit-specify` — capture the spec (what/why, user stories,
   acceptance criteria) under `specs/<feature>/spec.md`.
2. `/speckit-clarify` (optional) — resolve ambiguous edge cases first.
3. `/speckit-plan` — technical blueprint: architecture, data model, API
   contract.
4. `/speckit-tasks` — break the plan into small (1-4h), ordered, testable
   tasks.
5. `/speckit-analyze` (optional) — cross-check spec/plan/tasks consistency.
6. `/speckit-implement` — execute the tasks; tests + Pint must pass.

Product scope for those specs still comes from the Product Lead / founder —
Spec Kit changes *how* we build, not *who* decides *what* to build (see
`.specify/memory/constitution.md`, Principle III).

## Status

Initial scaffold — see issue SAA-3. Product niche/MVP scope is being defined
separately; this repo has no domain-specific code yet by design.
