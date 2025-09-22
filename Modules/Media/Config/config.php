<?php

return [
    'name' => 'Media',
    'is_cloud' => env('MEDIA_CLOUD_ENABLED', false),
    'cdn_enabled' => env('MEDIA_CLOUD_CDN_ENABLED', false),
    'cdn_url' => env('MEDIA_CLOUD_CDN_URL', 'https://dev-cdn.kartat.io/')
];
