<?php

return [
    /**
     * CDN origin for user-uploaded files on shared NFS (assets.durpalla.com).
     * When set, upload_asset() and hotel image URLs use this base instead of APP_URL.
     *
     * @see docs in durpalla-admin: docs/SHARED_ASSETS_CDN.md
     */
    'public_base_url' => rtrim((string) env('UPLOADS_PUBLIC_BASE_URL', ''), '/'),

    /**
     * Path prefixes that should resolve via public_base_url when it is set.
     */
    'cdn_path_prefixes' => [
        'uploads/',
        'nid/',
        'storage/',
        'vehicles/',
        'qrs/',
        'images/',
    ],
];
