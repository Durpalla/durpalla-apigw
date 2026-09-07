<?php

return [
    'hold_ttl_minutes' => (int) env('TOUR_HOLD_TTL_MINUTES', 15),
    'payment_window_minutes' => (int) env('TOUR_PAYMENT_WINDOW_MINUTES', 5),
    'search_default_limit' => (int) env('TOUR_SEARCH_LIMIT', 30),
    'image_public_base_url' => env(
        'TOUR_IMAGE_PUBLIC_BASE_URL',
        env('UPLOADS_PUBLIC_BASE_URL', env('APP_URL', ''))
    ),
];
