<?php

return [

    /*
    |--------------------------------------------------------------------------
    | OpenSearch (trip relevance)
    |--------------------------------------------------------------------------
    |
    | When enabled, trip list ordering uses OpenSearch BM25 + configured boosts.
    | Filtering for valid from/to pairs uses the same route rules as SQL search.
    | Set OPENSEARCH_ENABLED=false to use the legacy database-only path.
    |
    */
    'opensearch' => [
        'enabled' => (bool) env('OPENSEARCH_ENABLED', false),
        'base_url' => rtrim((string) env('OPENSEARCH_BASE_URL', ''), '/'),
        'index' => (string) env('OPENSEARCH_INDEX', 'durpalla_trip_schedules'),
        'username' => (string) env('OPENSEARCH_USERNAME', ''),
        'password' => (string) env('OPENSEARCH_PASSWORD', ''),
        'timeout' => (int) env('OPENSEARCH_TIMEOUT', 5),
    ],

    /*
    |--------------------------------------------------------------------------
    | Ranking weights (federated merge)
    |--------------------------------------------------------------------------
    |
    | final = w_os * norm(opensearch_score)
    |       + w_avail * availability_ratio
    |       + w_dep * departure_boost
    |
    | Partner rows use partner_base_score instead of opensearch_score.
    | partner_internal_blend: weight applied to internal block vs partner (0–1).
    |
    */
    'ranking' => [
        'weight_opensearch_score' => (float) env('TRIP_RANK_WEIGHT_OS', 1.0),
        'weight_availability' => (float) env('TRIP_RANK_WEIGHT_AVAIL', 0.15),
        'weight_departure_soon' => (float) env('TRIP_RANK_WEIGHT_DEPART', 0.05),
        'departure_window_hours' => (float) env('TRIP_RANK_DEPART_WINDOW_H', 36.0),
        'partner_base_score' => (float) env('TRIP_RANK_PARTNER_BASE', 0.85),
        'partner_internal_blend' => (float) env('TRIP_RANK_PARTNER_BLEND', 0.72),
    ],

    'cache' => [
        'ttl_seconds' => (int) env('TRIP_SEARCH_CACHE_TTL', 0),
    ],

    'partner' => [
        'enabled' => (bool) env('TRIP_PARTNER_API_ENABLED', false),
        'url' => (string) env('TRIP_PARTNER_API_URL', ''),
        'timeout' => (int) env('TRIP_PARTNER_TIMEOUT', 8),
        'cache_ttl_seconds' => (int) env('TRIP_PARTNER_CACHE_TTL', 60),
        'cache_key_prefix' => 'trip_partner:',
    ],

    'limits' => [
        'default_result_limit' => (int) env('TRIP_SEARCH_LIMIT', 10),
        'opensearch_fetch_limit' => (int) env('TRIP_SEARCH_OS_LIMIT', 50),
    ],

];
