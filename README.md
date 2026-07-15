# Durpalla API Gateway

Lightweight Laravel API service for customer app and related endpoints. Same database as the main Durpalla application — **no migrations** in this project.

## Setup

- **Database:** Point `DB_*` in `.env` to the **same database** as the main app (e.g. `DB_DATABASE=durpalla`). Do not run `php artisan migrate` in production; schema is managed by the main application. Migrations in this repo are **for the test database only** (run automatically via `RefreshDatabase` when tests run).
- **Application key:** `php artisan key:generate`
- **Passport (if used):** Install and use keys from main app or run `php artisan passport:install` once on the shared DB.

```bash
cp .env.example .env
# Edit .env: set DB_DATABASE, DB_USERNAME, DB_PASSWORD to match main app
php artisan key:generate
```

## Run

```bash
php artisan serve
# Or with Octane (recommended for production):
php artisan octane:start
```

## Structure

- **No modules** — flat Laravel: `app/Http`, `app/Models`, `app/Services`, `routes/`.
- **Small footprint** — API-only; minimal views and front-end assets.
- **Shared DB** — reads/writes the same MySQL as Durpalla; no migration files in this repo.

## Tests

Tests use a separate database (`apigw_test`). The test bootstrap creates the DB if missing; `RefreshDatabase` runs this project’s migrations (users, user_otps, customers, personal_access_tokens, Passport oauth_*) so the test DB has the required tables. Production continues to use the main app’s database without running these migrations.

```bash
php artisan test
```

## Load tests (booking ≥5 TPS)

k6 open-loop and closed-loop scripts live in [`loadtests/`](loadtests/README.md). Seed trip/seat inventory from the main Durpalla app:

```bash
cd ../durpalla && php artisan db:seed --class=TransportLoadTestSeeder
cd ../durpalla-apigw && ./loadtests/run.sh open
```
