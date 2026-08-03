<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | API gateway: allowlist browser origins only. Mobile native clients are
    | unaffected by CORS. Never use "*" with supports_credentials=true.
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env(
            'CORS_ALLOWED_ORIGINS',
            'https://admin.durpalla.com,https://web.durpalla.com,https://durpalla.com,https://www.durpalla.com'
        ))
    ))),

    // Any https://*.durpalla.com subdomain (web, apigw callers, staging hosts).
    'allowed_origins_patterns' => array_values(array_filter([
        '#^https://([a-z0-9-]+\.)*durpalla\.com$#i',
        env('APP_ENV') === 'local' ? '#^https?://(localhost|127\.0\.0\.1)(:\d+)?$#' : null,
        env('APP_ENV') === 'local' ? '#^https?://.*\.test(:\d+)?$#' : null,
        env('APP_ENV') === 'local' ? '#^https?://([a-z0-9-]+\.)*durpalla\.site(:\d+)?$#i' : null,
        env('APP_ENV') === 'local'
            ? '#^https?://(192\.168\.\d{1,3}\.\d{1,3}|10\.\d{1,3}\.\d{1,3}\.\d{1,3}|172\.(1[6-9]|2\d|3[01])\.\d{1,3}\.\d{1,3})(:\d+)?$#'
            : null,
    ])),

    'allowed_headers' => ['*'],

    'exposed_headers' => ['X-Guest-Id', 'X-Request-Id'],

    'max_age' => 600,

    'supports_credentials' => true,

];
