<?php

/**
 * Minimal audit config so Durpalla migrations that call config('audit.*')
 * work when loaded into apigw tests.
 */
return [
    'enabled' => env('AUDIT_ENABLED', true),
    'connection' => env('AUDIT_CONNECTION', 'audit'),
    'table' => 'audit_logs',
    'retention_days' => (int) env('AUDIT_RETENTION_DAYS', 180),
];
