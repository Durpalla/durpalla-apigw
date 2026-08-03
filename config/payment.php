<?php

return [
    /**
     * Browser checkout: apigw redirects /payment/status and gateway callbacks here.
     * Mobile apps should leave this unset (apigw status view + API polling).
     */
    'frontend_status_url' => env('FRONTEND_PAYMENT_STATUS_URL'),

    /** Colored app icon — success state uses full color; failed/pending use CSS filters. */
    'brand_icon_url' => rtrim((string) env('BRAND_ICON_URL', 'https://web.durpalla.com/logos/icon-colored-primary.png'), '/'),
];
