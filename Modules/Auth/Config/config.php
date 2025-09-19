<?php

return [
    'name' => 'Auth',
    'session_lifetime' => env('AUTH_SESSION_LIFETIME', 20),
    'user' => [
        'statuses' => [
            0 => 'Pending',
            1 => 'Active',
            9 => 'Inactive',
        ]
    ],
    'recaptcha' => [
        'site_key' => env('RECAPTCHA_SITE_KEY'),
        'site_secret' => env('RECAPTCHA_SITE_SECRET'),
    ]
];
