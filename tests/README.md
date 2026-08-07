# API Feature Tests

Feature tests for every API v1 route. Each test hits the HTTP endpoint and asserts status code (and JSON structure where applicable).

## Requirements

- **Database:** This project uses the **same database** as the main app; it has **no migrations**. For tests, use a dedicated test DB (e.g. `apigw_test`) with the schema already applied:
  - Create `apigw_test` and run migrations from the **main Durpalla project** (or copy schema from production), then point `phpunit.xml` / `.env.testing` at it.
  - Or use MySQL with `DB_DATABASE=apigw_test`; the test bootstrap creates the database if missing, but **tables must exist** (migrate from main app or a dump).

## Run all API tests

```bash
php artisan test tests/Feature/Api*.php
```

Or run by group:

```bash
php artisan test tests/Feature/ApiCustomerAuthTest.php
php artisan test tests/Feature/ApiAuthTest.php
php artisan test tests/Feature/ApiPublicInitTest.php
php artisan test tests/Feature/ApiCartTransportTest.php
php artisan test tests/Feature/ApiOrderBookingPaymentTest.php
php artisan test tests/Feature/ApiSupportDownloadTest.php
php artisan test tests/Feature/ApiMyTest.php
php artisan test tests/Feature/ApiQuickbookTest.php
php artisan test tests/Feature/ApiSupervisorTest.php
php artisan test tests/Feature/HotelBookingApiTest.php tests/Unit/HotelBookingServiceTest.php tests/Unit/HotelInventoryServiceTest.php
```

## Test coverage by route group

| Test class | Routes covered |
|------------|----------------|
| **ApiCustomerAuthTest** | `POST /api/v1/customer/auth/register`, `login`, `GET me`, `POST logout` |
| **ApiAuthTest** | `POST /api/v1/auth/login`, `register`, `check`, `verify`, `forgot`, `reset`, `otp/resend`, `POST logout`, `POST push/bind` |
| **ApiPublicInitTest** | `GET site/init`, `mobile/init`, `vehicles`, `offers`, `faq`, `search`, `available`, `transport/search`, `transport/available`, `suggest`, `trip/{id}`, `gateway`, `page/{slug}` |
| **ApiCartTransportTest** | `POST cart/add`, `cart/lock`, `cart/remove`, `cart/unlock`, `GET cart/reset`, `POST transport/lock`, `transport/unlock` |
| **ApiOrderBookingPaymentTest** | `POST order/confirm`, `order/transaction`, `transport/booking/confirm`, `booking/confirm`, `booking/payment`, `GET booking/check/{id}`, `POST booking/cancel`, `POST payment/make`, `payment/validate`, `GET payment/verify`, `POST coupon/validate` |
| **ApiSupportDownloadTest** | `POST support/send`, `POST download/link` |
| **ApiMyTest** | All `GET/POST /api/v1/my/*` (profile, bookings, cancellations, journey, notifications, etc.) – assert 401 without token |
| **ApiQuickbookTest** | All `GET/POST /api/v1/quickbook/*` – assert 401 without token |
| **ApiSupervisorTest** | All `GET/POST /api/v1/supervisor/*` – assert 401 without token |
| **HotelApiTest** | Smoke: `GET /api/v1/hotel/search`, `POST /api/v1/hotel/hold` / `hold/release` → 401 without token |
| **HotelBookingApiTest** | Quote, hold (+ idempotency / inventory), release, confirm, foreign-hold reject (`RefreshDatabase`) |
| **HotelPricingServiceTest** (Unit) | Nightly quote math (single night, multi-night) |
| **HotelBookingServiceTest** (Unit) | `createHold`/`releaseHold`/`confirmFromHold` with `Customer` (rejects staff `User`) |
| **HotelInventoryServiceTest** (Unit) | Night dates, apply/release hold, sold-out `RuntimeException` |

Hotel tables (`hotels`, `hotel_holds`, inventory, etc.) are created by Laravel migrations in **`/var/www/html/durpalla`** (see `database/migrations/2026_04_24_120000_create_hotel_tables.php`). Run `php artisan migrate` from **durpalla** against the DB this API uses; **durpalla-apigw** does not ship app migrations.

Hotel env keys: `HOTEL_HOLD_TTL_MINUTES`, `HOTEL_PAYMENT_WINDOW_MINUTES`, `HOTEL_SEARCH_LIMIT`, `HOTEL_SEARCH_RADIUS_KM` (see `config/hotel.php`). Phase 2 vendor adapters will extend the same config.
