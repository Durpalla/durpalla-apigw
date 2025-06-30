<?php
// SSLCommerz configuration
return [
    'projectPath' => env('PROJECT_PATH'),
    'apiDomain' => env("API_DOMAIN_URL", "https://sandbox.sslcommerz.com"),
    'apiCredentials' => [
        'store_id' => env("STORE_ID"),
        'store_password' => env("STORE_PASSWORD"),
    ],
    'apiUrl' => [
        'make_payment' => "/gwprocess/v4/api.php",
        'transaction_status' => "/validator/api/merchantTransIDvalidationAPI.php",
        'order_validate' => "/validator/api/validationserverAPI.php",
        'refund_payment' => "/validator/api/merchantTransIDvalidationAPI.php",
        'refund_status' => "/validator/api/merchantTransIDvalidationAPI.php",
    ],
    'connect_from_localhost' => env("IS_LOCALHOST", true), // For Sandbox, use "true", For Live, use "false"
    'sandbox_mode' => env("SSL_SANDBOX_MODE", FALSE),
    'store_id' => env("STORE_ID"),
    'store_password' => env("STORE_PASSWORD"),
    'default_currency' => env("STORE_CURRENCY"),
    'apis' => [
        'make_payment' => "/gwprocess/v4/api.php",
        'transaction_validate' => "/validator/api/merchantTransIDvalidationAPI.php",
        'order_validate' => "/validator/api/validationserverAPI.php",
        'refund_payment' => "/validator/api/merchantTransIDvalidationAPI.php",
        'refund_status' => "/validator/api/merchantTransIDvalidationAPI.php",
    ],
    'allow_localhost' => env("SSL_SANDBOX_MODE", FALSE),
    'success_url' => env("SSL_SUCCESS_URL", '/sslcommerz/success'),
    'fail_url' => env("SSL_FAIL_URL", '/sslcommerz/fail'),
    'cancel_url' => env("SSL_CANCEL_URL", '/sslcommerz/cancel'),
    'ipn_url' => env("SSL_IPN_URL", '/sslcommerz/ipn'),
];
