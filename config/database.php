<?php

use Illuminate\Support\Str;

$normalizeHost = static fn (?string $host, string $default = '127.0.0.1'): string => ($host === null || $host === '')
    ? $default
    : $host;

$redisPassword = env('REDIS_PASSWORD');
$redisPassword = ($redisPassword === null || $redisPassword === '' || $redisPassword === 'null')
    ? null
    : trim((string) $redisPassword, '"\'');

$redisHost = $normalizeHost(env('REDIS_HOST'));

$redisUrl = env('REDIS_URL');
if (! is_string($redisUrl) || $redisUrl === '') {
    $redisUrl = null;
} else {
    $parsedRedisUrl = parse_url($redisUrl);
    if (! isset($parsedRedisUrl['host']) || $parsedRedisUrl['host'] === '') {
        $redisUrl = null;
    }
}

$redisSentinelEnabled = filter_var(env('REDIS_SENTINEL_ENABLED', false), FILTER_VALIDATE_BOOL);

$redisSentinels = [
    ['host' => $normalizeHost(env('REDIS_SENTINEL_1_HOST')), 'port' => (int) env('REDIS_SENTINEL_1_PORT', 26379)],
    ['host' => $normalizeHost(env('REDIS_SENTINEL_2_HOST')), 'port' => (int) env('REDIS_SENTINEL_2_PORT', 26379)],
    ['host' => $normalizeHost(env('REDIS_SENTINEL_3_HOST')), 'port' => (int) env('REDIS_SENTINEL_3_PORT', 26379)],
];

$redisConnection = static function (int $database) use ($redisSentinelEnabled, $redisSentinels, $redisUrl, $redisHost, $redisPassword): array {
    if ($redisSentinelEnabled) {
        $config = [
            'sentinels' => $redisSentinels,
            'service' => env('REDIS_SENTINEL_SERVICE', 'mymaster'),
            'database' => $database,
        ];

        if ($redisPassword !== null) {
            $config['password'] = $redisPassword;
        }

        return $config;
    }

    return [
        'url' => $redisUrl,
        'host' => $redisHost,
        'username' => env('REDIS_USERNAME'),
        'password' => $redisPassword,
        'port' => (int) env('REDIS_PORT', 6379),
        'database' => $database,
    ];
};

return [

    /*
    |--------------------------------------------------------------------------
    | Default Database Connection Name
    |--------------------------------------------------------------------------
    |
    | Here you may specify which of the database connections below you wish
    | to use as your default connection for all database work. Of course
    | you may use many connections at once using the Database library.
    |
    */

    'default' => env('DB_CONNECTION', 'mysql'),

    /*
    |--------------------------------------------------------------------------
    | Database Connections
    |--------------------------------------------------------------------------
    |
    | Here are each of the database connections setup for your application.
    | Of course, examples of configuring each database platform that is
    | supported by Laravel is shown below to make development simple.
    |
    |
    | All database work in Laravel is done through the PHP PDO facilities
    | so make sure you have the driver for your particular database of
    | choice installed on your machine before you begin development.
    |
    */

    'connections' => [

        'sqlite' => [
            'driver' => 'sqlite',
            'url' => env('DATABASE_URL'),
            'database' => env('DB_DATABASE', database_path('database.sqlite')),
            'prefix' => '',
            'foreign_key_constraints' => env('DB_FOREIGN_KEYS', true),
        ],

        'mysql' => [
            'driver' => 'mysql',
            'url' => env('DATABASE_URL'),
            // MySQL Router: 6446 read/write primary, 6447 read-only (see durpalla/docs/mysql-innodb-cluster.md).
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '6446'),
            'database' => env('DB_DATABASE', 'forge'),
            'username' => env('DB_USERNAME', 'forge'),
            'password' => env('DB_PASSWORD', ''),
            'read' => [
                'host' => [env('DB_HOST', '127.0.0.1')],
                'port' => env('DB_READ_PORT', env('DB_PORT', '6446')),
            ],
            'write' => [
                'host' => [env('DB_HOST', '127.0.0.1')],
                'port' => env('DB_WRITE_PORT', env('DB_PORT', '6446')),
            ],
            'unix_socket' => env('DB_SOCKET', ''),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
            'options' => extension_loaded('pdo_mysql') ? array_filter([
                PDO::MYSQL_ATTR_SSL_CA => env('DB_SSL_CA'),
                PDO::MYSQL_ATTR_SSL_CERT => env('DB_SSL_CERT'),
                PDO::MYSQL_ATTR_SSL_KEY => env('DB_SSL_KEY'),
                // Do not set MYSQL_ATTR_CONNECT_TIMEOUT here — MySQL Router rejects it (SQL syntax error).
//                PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => false,
            ]) : [],
            'dump' => [
               'dump_binary_path' => '/var/www/backups', // only the path, so without `mysqldump` or `pg_dump`
               'use_single_transaction',
               'timeout' => 60 * 5, // 5 minute timeout
               // 'exclude_tables' => ['table1', 'table2'],
               // 'add_extra_option' => '--optionname=optionvalue',
            ]
        ],

        'pgsql' => [
            'driver' => 'pgsql',
            'url' => env('DATABASE_URL'),
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '5432'),
            'database' => env('DB_DATABASE', 'forge'),
            'username' => env('DB_USERNAME', 'forge'),
            'password' => env('DB_PASSWORD', ''),
            'charset' => 'utf8',
            'prefix' => '',
            'prefix_indexes' => true,
            'schema' => 'public',
            'sslmode' => 'prefer',
        ],

        'sqlsrv' => [
            'driver' => 'sqlsrv',
            'url' => env('DATABASE_URL'),
            'host' => env('DB_HOST', 'localhost'),
            'port' => env('DB_PORT', '1433'),
            'database' => env('DB_DATABASE', 'forge'),
            'username' => env('DB_USERNAME', 'forge'),
            'password' => env('DB_PASSWORD', ''),
            'charset' => 'utf8',
            'prefix' => '',
            'prefix_indexes' => true,
        ],

        'mongodb' => [
            'driver'   => 'mongodb',
            'dsn'      => env('MONGODB_URI', 'mongodb://127.0.0.1:27017'),
            'database' => env('MONGODB_DATABASE', 'app'),
            // optional auth-style config if not using DSN:
            // 'host' => env('MONGODB_HOST', '127.0.0.1'),
            // 'port' => env('MONGODB_PORT', 27017),
            // 'database' => env('MONGODB_DATABASE', 'app'),
            // 'username' => env('MONGODB_USERNAME', null),
            // 'password' => env('MONGODB_PASSWORD', null),
            // 'options'  => ['appname' => env('APP_NAME')],
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Migration Repository Table
    |--------------------------------------------------------------------------
    |
    | This table keeps track of all the migrations that have already run for
    | your application. Using this information, we can determine which of
    | the migrations on disk haven't actually been run in the database.
    |
    */

    'migrations' => 'migrations',

    /*
    |--------------------------------------------------------------------------
    | Redis Databases
    |--------------------------------------------------------------------------
    |
    | Redis is an open source, fast, and advanced key-value store that also
    | provides a richer body of commands than a typical key-value system
    | such as APC or Memcached. Laravel makes it easy to dig right in.
    |
    */

    'redis' => [

        'client' => env('REDIS_CLIENT', 'phpredis'),

        'options' => array_filter([
            'cluster' => $redisSentinelEnabled ? null : env('REDIS_CLUSTER', 'redis'),
            'prefix' => env('REDIS_PREFIX', Str::slug(env('APP_NAME', 'laravel'), '_').'_database_'),
            'parameters' => array_filter([
                'password' => $redisPassword,
            ], static fn ($value) => $value !== null && $value !== '' && $value !== 'null'),
        ], static fn ($value) => $value !== null),

        'default' => $redisConnection((int) env('REDIS_DB', 0)),

        'cache' => $redisConnection((int) env('REDIS_CACHE_DB', 1)),

    ],

];