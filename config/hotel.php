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
];
