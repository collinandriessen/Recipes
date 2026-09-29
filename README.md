# SaaS Corp — RecipeFit Application

Laravel + Livewire application for RecipeFit (a personal recipe organizer
combining macro/fitness tracking and allergen filtering). Product scope is
confirmed by the founder/Product Lead — see the architecture doc on issue
SAA-7 for the full technical design and the roadmap on SAA-6.

## Stack

- PHP 8.3
- Laravel 13.x (latest stable)
- Livewire 4.x (latest stable)
- SQLite for local development (swap to MySQL/Postgres per environment via `.env`)
- Redis + Laravel Horizon for background jobs (recipe import, nutrition/allergen computation)
- Vite + Tailwind CSS 4 for the frontend build
- Pest/PHPUnit for tests, Laravel Pint for code style
- GitHub Actions CI (lint + test on every push/PR, with a Redis service container)

## Requirements

- PHP >= 8.3 with the standard Laravel extensions (`mbstring`, `dom`,
  `fileinfo`, `sqlite3`/`pdo_sqlite`, `curl`, `intl`, `zip`, `bcmath`, `gd`,
  `posix`, `redis`)
- Composer 2.x
- Node.js 20+ and npm
- Redis server (required before running Horizon; local dev can stay on
  `QUEUE_CONNECTION=database` until you need it)

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

- `/` — welcome page
- `/register`, `/login` — auth (Livewire components, session-guard based)
- `/dashboard` — authenticated landing page (placeholder; recipe features
  land in Phase 2.1+)
- `/horizon` — Horizon dashboard (queue monitoring; requires Redis running)

## Authentication

Auth is a first-party Livewire scaffold (no Breeze/Fortify dependency):

- `App\Livewire\Auth\Login` / `App\Livewire\Auth\Register` — full-page Livewire
  components at `/login` and `/register`, guarded by the `guest` middleware.
- `App\Http\Controllers\Auth\LogoutController` — `POST /logout`, guarded by
  `auth` middleware.
- Login is rate-limited (5 attempts per email+IP per 60s) via `RateLimiter`.
- `users` table (see `database/migrations/0001_01_01_000000_create_users_table.php`
  and `..._add_subscription_tier_to_users_table.php`): `email`, `password`,
  `subscription_tier` (`free` | `paid`, default `free`), `stripe_customer_id`
  (nullable) — matches architecture doc §2. Billing integration is not wired
  up yet; the column just exists for Phase 2.3+.

## Background jobs (Redis + Horizon)

Laravel Horizon manages queue workers and gives a dashboard at `/horizon`.

Local setup:

```bash
# start Redis (pick one)
redis-server
# or: docker run -p 6379:6379 redis:7

# set in .env
QUEUE_CONNECTION=redis

# run Horizon (keeps running, processes queued jobs)
php artisan horizon
```

In production, run `php artisan horizon` under a process supervisor
(systemd/Supervisor) so it restarts on crash and on deploy
(`php artisan horizon:terminate` for graceful restarts).

No queued jobs exist yet in this phase — Horizon is wired up and ready for
Phase 2.2 (recipe import + nutrition computation background jobs).

## Environment configuration

Copy `.env.example` to `.env` and adjust as needed. Key variables:

| Variable | Purpose | Local default |
|---|---|---|
| `APP_ENV` | Environment name | `local` |
| `APP_KEY` | Encryption key, generate with `php artisan key:generate` | (generated) |
| `APP_URL` | Base URL used by generated links | `http://localhost:8000` |
| `DB_CONNECTION` | Database driver | `sqlite` |
| `DB_DATABASE` | SQLite file path (or db name for other drivers) | `database/database.sqlite` |
| `QUEUE_CONNECTION` | Queue driver — `database` (no infra) or `redis` (Horizon) | `database` |
| `REDIS_HOST` / `REDIS_PORT` | Redis connection, used by queues/Horizon | `127.0.0.1` / `6379` |
| `HORIZON_NAME` | Optional label shown in the Horizon dashboard | unset |

For non-SQLite databases, uncomment/set the `DB_*` host/port/username/password
variables in `.env`.

## Testing

```bash
./vendor/bin/phpunit
# or
php artisan test
```

Auth flows are covered in `tests/Feature/Auth/AuthTest.php` (registration,
login, rate limiting, logout, guest/auth route guarding) using
`Livewire::test(...)`.

## Code style

Laravel Pint enforces PHP code style (PSR-12-based):

```bash
./vendor/bin/pint          # auto-fix
./vendor/bin/pint --test   # check only, no changes (used in CI)
```

## CI

`.github/workflows/ci.yml` runs on every push/PR to `main`, with a Redis
service container available (matches the Horizon/queue dependency):

1. Install PHP + Composer dependencies (`posix`, `redis` extensions included)
2. Install Node + npm dependencies, build assets
3. Generate app key, create SQLite DB, run migrations
4. Lint with Pint (`--test`, fails on violations)
5. Run the test suite

A deploy pipeline is not yet built (basic lint+test-on-push satisfies MVP
scope per SAA-14); tracked as a follow-up once a hosting target is chosen.

## Project conventions

- New features: prefer Livewire full-page components under
  `app/Livewire` (or `resources/views/components/*.blade.php` single-file
  components) wired to routes in `routes/web.php`.
- Auth-gated routes go under the `auth` middleware group in `routes/web.php`;
  guest-only routes (login/register) under `guest`.
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

Phase 2.0 (app skeleton, auth scaffold, Redis/Horizon, base layout) — see
issue SAA-14. Phase 1 data model (recipes/ingredients/nutrition/allergens)
landed prior to this. Recipe import, filtering, and meal planning land in
Phase 2.1+.
