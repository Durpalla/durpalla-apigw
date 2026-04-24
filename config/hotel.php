<?php

/**
 * Local hotel inventory (Phase 1). Phase 2: add `enabled_vendors`, credentials,
 * markup %, and cache TTL; implement App\Contracts\HotelVendorContract adapters
 * and reconcile vendor holds before marking paid.
 */
return [
    /**
     * Hotel search diagnostics. If `HOTEL_SEARCH_DEBUG` is unset, follows `APP_DEBUG`
     * so local installs log without an extra env key. Set `HOTEL_SEARCH_DEBUG=false` to
     * force off when `APP_DEBUG=true`. Uses `Log::warning` + `error_log` so messages
     * still appear when `LOG_LEVEL=error` (Monolog may drop `info`).
     */
    'debug_search' => env('HOTEL_SEARCH_DEBUG') !== null
        ? (bool) filter_var(env('HOTEL_SEARCH_DEBUG'), FILTER_VALIDATE_BOOLEAN)
        : (bool) filter_var(env('APP_DEBUG', false), FILTER_VALIDATE_BOOLEAN),
    'hold_ttl_minutes' => (int) env('HOTEL_HOLD_TTL_MINUTES', 15),
    'payment_window_minutes' => (int) env('HOTEL_PAYMENT_WINDOW_MINUTES', 10),
    'search_default_limit' => (int) env('HOTEL_SEARCH_LIMIT', 30),
    /** Used when `lat` + `lng` are passed to `GET hotel/search` (kilometres). */
    'search_radius_km' => (float) env('HOTEL_SEARCH_RADIUS_KM', 50),
    /**
     * When `check_in` / `check_out` are both absent (e.g. client sends only `city`),
     * use today → tomorrow so search still runs. Set `HOTEL_SEARCH_DEFAULT_DATES=false`
     * to return empty results instead (strict API).
     */
    'search_default_stay_when_dates_missing' => (bool) filter_var(
        env('HOTEL_SEARCH_DEFAULT_DATES', true),
        FILTER_VALIDATE_BOOLEAN
    ),
    /**
     * Public base URL for Module Hotel `hotel_images.image_path` and storage-relative paths.
     * If uploads use `php artisan storage:link` on the **admin** host, set this to the admin
     * origin (not the API host), e.g. `HOTEL_IMAGE_PUBLIC_BASE_URL=https://admin.durpalla.com`
     * Full URLs in the DB that start with `/storage/...` (including when saved as apigw) are
     * rewritten to this origin in API responses.
     */
    'image_public_base_url' => env('HOTEL_IMAGE_PUBLIC_BASE_URL', env('APP_URL', '')),
    /**
     * When per-night `hotel_inventories` rows are missing, `assertAvailability` throws and the
     * API would mark the room as unavailable. Set to true to treat that as available so clients
     * (and ops without seeded inventory) can still proceed; set to false in production with full inventory.
     */
    'rooms_treat_missing_inventory_as_available' => (bool) filter_var(
        env('HOTEL_ROOMS_TREAT_MISSING_INVENTORY_AS_AVAILABLE', true),
        FILTER_VALIDATE_BOOLEAN
    ),
    /**
     * When missing-inventory mode is on, auto-seeded `hotel_inventory` rows use this `units_total`
     * for non–module-linked room types. Module `mod_hr_*` types use `hotel_rooms.total_rooms` when present.
     */
    'default_inventory_units_per_night' => (int) env('HOTEL_DEFAULT_INVENTORY_UNITS', 10),
    /**
     * Fallback weekday list (0=Sun..6=Sat) when no `hotel_peak_day_rules` row matches.
     * Should match `Modules/Hotel/Config` `peak_days` (default Fri–Sat). Comma-separated, e.g. "5,6".
     */
    'default_peak_days' => array_values(array_filter(array_map('intval', explode(',', (string) env('HOTEL_DEFAULT_PEAK_DAYS', '5,6'))))),

    /**
     * Legacy: month-based pricing hint (not used by customer API `quoteStay` after peak-day rules);
     * kept for possible tooling. Comma-separated months 1–12.
     */
    'module_peak_months' => array_filter(array_map('intval', explode(',', (string) env('HOTEL_MODULE_PEAK_MONTHS', '11,12,1')))),
];
