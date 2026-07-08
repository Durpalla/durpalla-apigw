<?php

return [
    'android' => [
        'min_version' => env('APP_ANDROID_MIN_VERSION', '1.0.0'),
        'latest_version' => env('APP_ANDROID_LATEST_VERSION', '1.0.0'),
        'force_update' => (bool) env('APP_ANDROID_FORCE_UPDATE', false),
        'store_url' => env('APP_ANDROID_STORE_URL'),
    ],
    'ios' => [
        'min_version' => env('APP_IOS_MIN_VERSION', '1.0.0'),
        'latest_version' => env('APP_IOS_LATEST_VERSION', '1.0.0'),
        'force_update' => (bool) env('APP_IOS_FORCE_UPDATE', false),
        'store_url' => env('APP_IOS_STORE_URL'),
    ],
    'sections' => [
        'my_offers' => (bool) env('APP_SECTION_MY_OFFERS', true),
        'my_trips' => (bool) env('APP_SECTION_MY_TRIPS', true),
        'upcoming_trips' => (bool) env('APP_SECTION_UPCOMING_TRIPS', true),
        'gallery_slider' => (bool) env('APP_SECTION_GALLERY_SLIDER', true),
    ],
];
