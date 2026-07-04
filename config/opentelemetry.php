<?php

return [

    'enabled' => env('OTEL_ENABLED', false),

    'service_name' => env('OTEL_SERVICE_NAME', env('APP_NAME', 'durpalla-apigw')),

    'collector_endpoint' => rtrim(env('OTEL_EXPORTER_OTLP_ENDPOINT', 'http://host.docker.internal:4318'), '/'),

    'organization' => env('OPENOBSERVE_ORG', 'default'),

    'ingestion_email' => env('OPENOBSERVE_EMAIL'),

    'ingestion_token' => env('OPENOBSERVE_INGESTION_TOKEN'),

    'sample_rate' => (float) env('OTEL_TRACES_SAMPLER_ARG', 1.0),

];
