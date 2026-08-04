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

];
