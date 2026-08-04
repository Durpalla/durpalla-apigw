<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Invoice company name
    |--------------------------------------------------------------------------
    |
    | Legal / display name shown on the shared booking invoice HTML.
    | Keep this separate from APP_NAME (which may be an internal service
    | label such as "APIGW Durpalla").
    |
    */

    'company_name' => env('INVOICE_COMPANY_NAME', 'Durpalla Limited'),

    /*
    |--------------------------------------------------------------------------
    | Invoice company logo
    |--------------------------------------------------------------------------
    |
    | Absolute URL for the Durpalla brand mark on invoices (header/footer).
    |
    */

    'company_logo_url' => env(
        'INVOICE_COMPANY_LOGO_URL',
        'https://web.durpalla.com/logos/logo-horizontal-colored-premium.png'
    ),

    /*
    |--------------------------------------------------------------------------
    | Public assets base for merchant logos on invoices
    |--------------------------------------------------------------------------
    |
    | Used when merchant logo paths (e.g. logos/merchants/…) are not served
    | from APP_URL. Prefer the shared CDN / admin public origin.
    |
    */

    'assets_base_url' => rtrim((string) env(
        'INVOICE_ASSETS_BASE_URL',
        env('UPLOADS_PUBLIC_BASE_URL', 'https://admin.durpalla.com')
    ), '/'),

    /*
    |--------------------------------------------------------------------------
    | Local filesystem roots that may contain uploaded logos
    |--------------------------------------------------------------------------
    |
    | Checked in order so invoices can embed merchant logos as data URIs
    | when the files live on shared disk (more reliable than remote URLs).
    |
    */

    'local_asset_roots' => array_values(array_filter([
        resource_path('invoice-assets'),
        env('INVOICE_LOCAL_ASSETS_PATH'),
        public_path(),
        // Sibling main Durpalla app public (shared server layouts).
        dirname(base_path()).'/durpalla/public',
        '/var/www/html/durpalla/public',
    ])),

];
