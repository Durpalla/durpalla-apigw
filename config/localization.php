<?php

return [
    'default_locale' => 'en',

    'bundled_locales' => ['en', 'bn'],

    'remote_locales' => ['hi', 'ar', 'zh', 'ur', 'fa', 'tr', 'es', 'it'],

    'locales' => [
        'en' => ['name' => 'English', 'native_name' => 'English', 'direction' => 'ltr'],
        'bn' => ['name' => 'Bangla', 'native_name' => 'বাংলা', 'direction' => 'ltr'],
        'hi' => ['name' => 'Hindi', 'native_name' => 'हिन्दी', 'direction' => 'ltr'],
        'ar' => ['name' => 'Arabic', 'native_name' => 'العربية', 'direction' => 'rtl'],
        'zh' => ['name' => 'Chinese', 'native_name' => '中文', 'direction' => 'ltr'],
        'ur' => ['name' => 'Urdu', 'native_name' => 'اردو', 'direction' => 'rtl'],
        'fa' => ['name' => 'Farsi', 'native_name' => 'فارسی', 'direction' => 'rtl'],
        'tr' => ['name' => 'Turkish', 'native_name' => 'Türkçe', 'direction' => 'ltr'],
        'es' => ['name' => 'Spanish', 'native_name' => 'Español', 'direction' => 'ltr'],
        'it' => ['name' => 'Italian', 'native_name' => 'Italiano', 'direction' => 'ltr'],
    ],

    'apps' => [
        'customer-app' => [
            'label' => 'Durpalla Customer (Flutter)',
            'format' => 'arb-flat',
            'bundled_locales' => ['en', 'bn'],
        ],
        'merchant-desk' => [
            'label' => 'Durpalla Merchant Desk (Flutter)',
            'format' => 'arb-flat',
            'bundled_locales' => ['en', 'bn'],
        ],
        'web-merchant' => [
            'label' => 'Durpalla Merchant Web',
            'format' => 'i18next-namespaces',
            'bundled_locales' => ['en', 'bn'],
        ],
        'web-customer' => [
            'label' => 'Durpalla Customer Web',
            'format' => 'i18next-namespaces',
            'bundled_locales' => ['en', 'bn'],
        ],
    ],

    /** @deprecated Legacy flat files; use app-specific paths */
    'legacy_default_app' => 'web-customer',
];
