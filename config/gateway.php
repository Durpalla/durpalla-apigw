<?php
 return [
     'bkash' => [
         'env' => env('BKASH_ENV', 'sandbox'),
         'frontend_url' => env('FRONTEND_PAYMENT_STATUS_URL'),
         'sandbox' => [
             'app_key' => env('BKASH_SANDBOX_APP_KEY'),
             'app_secret' => env('BKASH_SANDBOX_APP_SECRET'),
             'username' => env('BKASH_SANDBOX_USERNAME'),
             'password' => env('BKASH_SANDBOX_PASSWORD'),
             'currency' => env('BKASH_SANDBOX_CURRENCY', 'BDT'),
             'version' => env('BKASH_SANDBOX_VERSION', 'v1.2.0-beta'),
             'endpoints' => [
                 'token' => env('BKASH_SANDBOX_ENDPOINT_TOKEN'),
                 'refresh_token' => env('BKASH_SANDBOX_ENDPOINT_REFRESH_TOKEN'),
                 'create' => env('BKASH_SANDBOX_ENDPOINT_CREATE'),
                 'execute' => env('BKASH_SANDBOX_ENDPOINT_EXECUTE'),
                 'verify' => env('BKASH_SANDBOX_ENDPOINT_VERIFY'),
                 'refund' => env('BKASH_SANDBOX_ENDPOINT_REFUND'),
                 'redirect' => env('BKASH_SANDBOX_ENDPOINT_REDIRECT_URL'),
                 'callback' => env('BKASH_SANDBOX_ENDPOINT_CALLBACK')
             ]
         ]
     ],
     'nagad' => [
         'env' => env('NAGAD_ENV', 'sandbox'),
         'frontend_url' => env('FRONTEND_PAYMENT_STATUS_URL'),
         'sandbox' => [
             'currency' => env('NAGAD_SANDBOX_CURRENCY', 'BDT'),
             'version' => 'v-0.2.0', // or 'v-3.0.1' if serviceFee used
             'locale' => 'EN',
             'client_type' => 'PC_WEB', // MOBILE_APP, etc.
             'merchant_id' => env('NAGAD_SANDBOX_MERCHANT_ID'),
             'merchant_mobile' => env('NAGAD_SANDBOX_MERCHANT_MOBILE'),
             'private_key' => env('NAGAD_SANDBOX_PRIVATE_KEY'),
             'public_key' => env('NAGAD_SANDBOX_PUBLIC_KEY'),
             'currency_code' => env('NAGAD_SANDBOX_CURRENCY_CODE', '050'),
             'endpoints' => [
                 'create' => env('NAGAD_SANDBOX_ENDPOINT_CREATE'),
                 'execute' => env('NAGAD_SANDBOX_ENDPOINT_EXECUTE'),
                 'verify' => env('NAGAD_SANDBOX_ENDPOINT_VERIFY'),
                 'refund' => env('NAGAD_SANDBOX_ENDPOINT_REFUND'),
                 'callback' => env('NAGAD_SANDBOX_ENDPOINT_CALLBACK')
             ]
         ]
     ]
 ];
