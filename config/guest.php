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
        // Share across web.durpalla.com ↔ apigw.durpalla.com (leading dot).
        'domain' => env('GUEST_COOKIE_DOMAIN', '.durpalla.com'),
        'secure' => filter_var(env('GUEST_COOKIE_SECURE', true), FILTER_VALIDATE_BOOLEAN),
        'http_only' => true,
        // Credentialed XHR from another subdomain is cross-origin; browsers reject
        // Set-Cookie with Lax/Strict in that context. Use None+Secure for *.durpalla.com.
        'same_site' => env('GUEST_COOKIE_SAME_SITE', 'none'),
    ],

    /*
    | Header fallback for clients that don't send cookies (e.g. mobile apps).
    | Backend can echo the encrypted guest ID in response header so client
    | can send it as X-Guest-Id on subsequent requests.
    */
    'header_name' => env('GUEST_HEADER_NAME', 'X-Guest-Id'),

];
