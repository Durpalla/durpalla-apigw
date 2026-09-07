<?php

return [
    'hold_ttl_minutes' => (int) env('BOAT_HOLD_TTL_MINUTES', 15),
    'rfq_expiry_hours' => (int) env('BOAT_RFQ_EXPIRY_HOURS', 24),
    'search_default_limit' => (int) env('BOAT_SEARCH_LIMIT', 30),
    'min_bid_amount' => (float) env('BOAT_MIN_BID_AMOUNT', 1),
    'image_public_base_url' => env('BOAT_IMAGE_PUBLIC_BASE_URL', env('UPLOADS_PUBLIC_BASE_URL', env('APP_URL', ''))),
];
