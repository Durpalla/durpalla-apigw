# Tech context — durpalla-apigw

## Stack

- PHP 8.4, Laravel 12
- Passport (+ Sanctum present), Guzzle, Predis, Pusher, Firebase PHP, mPDF, OpenTelemetry
- Production often Octane; Docker compose + CI deploy scripts

## Local / deploy

- `.env` DB_* must match main Durpalla DB
- Passport RSA keys must match main app (volume / env seeding — see README)
- Do **not** run production migrations from this repo
- Load tests: `loadtests/` (k6); seed inventory from main app seeder

## Constraints

- Token-efficient agent edits: ≤5 files / ≤500 lines; never open `vendor/`, `storage/`, `bootstrap/cache/`
- Never wipe controllers/routes/config as a “cleanup”
