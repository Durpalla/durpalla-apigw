# System patterns — durpalla-apigw

## Architecture

- Flat Laravel (no modules): `app/Http`, `app/Models`, `app/Services`, `routes/`
- Controllers thin → **Services** + **Repository-style** data access
- Validation via **FormRequest**
- Routes versioned under `routes/api/v1/`

## Critical protected files

Never truncate/delete/stub unless user explicitly asks:

- `app/Http/Controllers/Api/v1/MyApiController.php`
- `app/Http/Controllers/Api/v1/AppConfigController.php`
- `routes/api/v1/customer.php`
- `config/app_mobile.php`

Rules/docs-only tasks must not touch application PHP.

## Schema

- Production DB = main Durpalla DB
- Migrations for production schema live in **`/var/www/html/durpalla`**, not here
- Tests may use separate `apigw_test` DB with this project's test migrations

## Seat layout (API builder)

`cabin_row` = vertical column left→right; `cabin_position` = top→bottom within column. Clients consume pre-grouped floor maps.
