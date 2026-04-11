<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Guest identification (cart / cabin locks)
    |--------------------------------------------------------------------------
    | Backend generates a unique guest ID, stores it encrypted in a cookie.
    | Frontend stores the cookie and sends it with every request so guest
    | users are identified for cart items and cabin locks.
    */

    'cookie' => [
        'name' => env('GUEST_COOKIE_NAME', 'guest_unique_id'),
        'minutes' => (int) env('GUEST_COOKIE_MINUTES', 60 * 24 * 30), // 30 days
        'path' => '/',
        'domain' => env('GUEST_COOKIE_DOMAIN'),
        'secure' => env('GUEST_COOKIE_SECURE', true),
        'http_only' => true,
        'same_site' => env('GUEST_COOKIE_SAME_SITE', 'lax'), // lax, strict, or none (use none for cross-domain)
    ],

    /*
    | Header fallback for clients that don't send cookies (e.g. mobile apps).
    | Backend can echo the encrypted guest ID in response header so client
    | can send it as X-Guest-Id on subsequent requests.
    */
    'header_name' => env('GUEST_HEADER_NAME', 'X-Guest-Id'),

];
