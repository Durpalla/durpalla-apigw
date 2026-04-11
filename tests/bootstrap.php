<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Ensure MySQL test database exists (when using mysql connection).
if (getenv('DB_CONNECTION') === 'mysql' && getenv('DB_DATABASE')) {
    try {
        $host = env('DB_HOST', '127.0.0.1');
        $port = env('DB_PORT', '3306');
        $user = env('DB_USERNAME', 'root');
        $pass = env('DB_PASSWORD', '');
        $db = getenv('DB_DATABASE');
        $dsn = "mysql:host=$host;port=$port;charset=utf8mb4";
        $pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `" . str_replace('`', '``', $db) . "`");
    } catch (Throwable $e) {
        // Ignore; tests will fail with clear "unknown database" if needed.
    }
}

// Set Passport keys for testing so Passport service provider can boot without storage/oauth-*.key files.
if (! getenv('PASSPORT_PUBLIC_KEY')) {
    $config = ['private_key_bits' => 512];
    $res = openssl_pkey_new($config);
    if ($res !== false) {
        openssl_pkey_export($res, $priv);
        $details = openssl_pkey_get_details($res);
        $pub = $details['key'] ?? '';
        if ($priv !== false && $pub !== '') {
            putenv('PASSPORT_PRIVATE_KEY=' . $priv);
            putenv('PASSPORT_PUBLIC_KEY=' . $pub);
            $_ENV['PASSPORT_PRIVATE_KEY'] = $priv;
            $_ENV['PASSPORT_PUBLIC_KEY'] = $pub;
        }
    }
}
