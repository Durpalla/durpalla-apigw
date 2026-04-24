<?php

/**
 * Local hotel inventory (Phase 1). Phase 2: add `enabled_vendors`, credentials,
 * markup %, and cache TTL; implement App\Contracts\HotelVendorContract adapters
 * and reconcile vendor holds before marking paid.
 */
return [
    'hold_ttl_minutes' => (int) env('HOTEL_HOLD_TTL_MINUTES', 15),
    'payment_window_minutes' => (int) env('HOTEL_PAYMENT_WINDOW_MINUTES', 10),
    'search_default_limit' => (int) env('HOTEL_SEARCH_LIMIT', 30),
    /** Used when `lat` + `lng` are passed to `GET hotel/search` (kilometres). */
    'search_radius_km' => (float) env('HOTEL_SEARCH_RADIUS_KM', 50),
];
