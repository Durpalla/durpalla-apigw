# Durpalla API Gateway

Lightweight Laravel API service for customer app and related endpoints. Same database as the main Durpalla application — **no migrations** in this project.

## Setup

- **Database:** Point `DB_*` in `.env` to the **same database** as the main app (e.g. `DB_DATABASE=durpalla`). Do not run `php artisan migrate` in production; schema is managed by the main application. Migrations in this repo are **for the test database only** (run automatically via `RefreshDatabase` when tests run).
- **Application key:** `php artisan key:generate`
- **Passport:** Use the **same** RSA key pair as the main Durpalla app (tokens must validate across services).

  **Production (Docker / CI deploy):** keys are stored on the `apigw-storage` volume as `storage/oauth-private.key` and `storage/oauth-public.key`. On each deploy, `script/ensure-passport-keys.php` runs before `config:cache`:

  1. If key files already exist on the volume, they are kept (never overwritten).
  2. Otherwise keys are copied from `PASSPORT_PRIVATE_KEY` / `PASSPORT_PUBLIC_KEY` in `/opt/durpalla-apigw/.env`.

  Add multiline PEM values to `.env` (or copy key files from the main app):

  ```env
  PASSPORT_PRIVATE_KEY="-----BEGIN RSA PRIVATE KEY-----
  ...
  -----END RSA PRIVATE KEY-----"

  PASSPORT_PUBLIC_KEY="-----BEGIN PUBLIC KEY-----
  ...
  -----END PUBLIC KEY-----"
  ```

  After the first successful deploy, keys persist in the Docker volume even if `.env` entries are removed.

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
