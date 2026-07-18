<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Authentication Defaults
    |--------------------------------------------------------------------------
    */

    'defaults' => [
        'guard' => env('AUTH_GUARD', 'api'),
        'passwords' => env('AUTH_PASSWORD_BROKER', 'users'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Authentication Guards
    |--------------------------------------------------------------------------
    |
    | Merchant / merchant_staff: session for web dashboards; *_api Sanctum
    | guards for API tokens (same providers). Customer/agent/partner are
    | Sanctum-only (API-first), matching the existing customer pattern.
    |
    */

    'guards' => [
        'web' => [
            'driver' => 'session',
            'provider' => 'users',
        ],

        'api' => [
            'driver' => 'passport',
            'provider' => 'users',
        ],

        'customer' => [
            'driver' => 'sanctum',
            'provider' => 'customers',
        ],

        // Merchant owner – web dashboard (session)
        'merchant' => [
            'driver' => 'session',
            'provider' => 'merchants',
        ],

        // Merchant owner – API (Sanctum)
        'merchant_api' => [
            'driver' => 'sanctum',
            'provider' => 'merchants',
        ],

        // Merchant staff – web dashboard (session)
        'merchant_staff' => [
            'driver' => 'session',
            'provider' => 'merchant_staff',
        ],

        // Merchant staff – API (Sanctum)
        'merchant_staff_api' => [
            'driver' => 'sanctum',
            'provider' => 'merchant_staff',
        ],

        'agent' => [
            'driver' => 'sanctum',
            'provider' => 'agents',
        ],

        'partner' => [
            'driver' => 'sanctum',
            'provider' => 'partners',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | User Providers
    |--------------------------------------------------------------------------
    */

    'providers' => [
        'users' => [
            'driver' => 'eloquent',
            // API gateway always uses App\Models\User (Passport). Do not override via AUTH_MODEL —
            // admin copies of .env may set Modules\Auth\Entities\User and break createToken().
            'model' => App\Models\User::class,
        ],

        'customers' => [
            'driver' => 'eloquent',
            'model' => App\Models\Customer::class,
        ],

        'merchants' => [
            'driver' => 'eloquent',
            'model' => App\Models\Merchant::class,
        ],

        'merchant_staff' => [
            'driver' => 'eloquent',
            'model' => App\Models\MerchantStaff::class,
        ],

        'agents' => [
            'driver' => 'eloquent',
            'model' => App\Models\Agent::class,
        ],

        'partners' => [
            'driver' => 'eloquent',
            'model' => App\Models\Partner::class,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Resetting Passwords
    |--------------------------------------------------------------------------
    */

    'passwords' => [
        'users' => [
            'provider' => 'users',
            'table' => env('AUTH_PASSWORD_RESET_TOKEN_TABLE', 'password_reset_tokens'),
            'expire' => 60,
            'throttle' => 60,
        ],
        'customers' => [
            'provider' => 'customers',
            'table' => 'customer_password_reset_tokens',
            'expire' => 60,
            'throttle' => 60,
        ],
        'merchants' => [
            'provider' => 'merchants',
            'table' => 'password_reset_tokens',
            'expire' => 60,
            'throttle' => 60,
        ],
        'merchant_staff' => [
            'provider' => 'merchant_staff',
            'table' => 'password_reset_tokens',
            'expire' => 60,
            'throttle' => 60,
        ],
        'agents' => [
            'provider' => 'agents',
            'table' => 'password_reset_tokens',
            'expire' => 60,
            'throttle' => 60,
        ],
        'partners' => [
            'provider' => 'partners',
            'table' => 'password_reset_tokens',
            'expire' => 60,
            'throttle' => 60,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Password Confirmation Timeout
    |--------------------------------------------------------------------------
    */

    'password_timeout' => env('AUTH_PASSWORD_TIMEOUT', 10800),

];
