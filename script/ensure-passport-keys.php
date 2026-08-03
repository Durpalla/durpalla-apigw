#!/usr/bin/env php
<?php

/**
 * Ensure Laravel Passport RSA keys exist on the persistent storage volume.
 *
 * Priority:
 * 1. Keep existing valid keys in storage/oauth-*.key (never overwrite).
 * 2. Materialize keys from PASSPORT_PRIVATE_KEY / PASSPORT_PUBLIC_KEY in .env.
 *
 * Exits non-zero when no valid keys are available (deploy should fail).
 */

declare(strict_types=1);

$root = dirname(__DIR__);

if (! is_file($root.'/vendor/autoload.php')) {
    fwrite(STDERR, "ERROR: vendor/autoload.php not found — run from apigw project root.\n");
    exit(1);
}

require $root.'/vendor/autoload.php';

$app = require $root.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$privatePath = storage_path('oauth-private.key');
$publicPath = storage_path('oauth-public.key');

function normalizePem(string $key): string
{
    return str_replace('\\n', "\n", trim($key));
}

function isValidPrivatePem(string $pem): bool
{
    $key = openssl_pkey_get_private($pem);

    return $key !== false;
}

function isValidPublicPem(string $pem): bool
{
    $key = openssl_pkey_get_public($pem);

    return $key !== false;
}

function storageKeysValid(string $privatePath, string $publicPath): bool
{
    if (! is_readable($privatePath) || ! is_readable($publicPath)) {
        return false;
    }

    $private = file_get_contents($privatePath);
    $public = file_get_contents($publicPath);

    if (! is_string($private) || ! is_string($public) || $private === '' || $public === '') {
        return false;
    }

    return isValidPrivatePem($private) && isValidPublicPem($public);
}

function passportKeysFromEnvFile(string $envPath): array
{
    if (! is_readable($envPath)) {
        return [null, null];
    }

    try {
        $repository = Dotenv\Repository\RepositoryBuilder::createWithNoAdapters()
            ->addAdapter(Dotenv\Repository\Adapter\EnvConstAdapter::class)
            ->addAdapter(Dotenv\Repository\Adapter\PutenvAdapter::class)
            ->immutable()
            ->make();

        $dotenv = Dotenv\Dotenv::create($repository, dirname($envPath), basename($envPath));
        $dotenv->load();

        $private = $_ENV['PASSPORT_PRIVATE_KEY'] ?? $_SERVER['PASSPORT_PRIVATE_KEY'] ?? getenv('PASSPORT_PRIVATE_KEY') ?: null;
        $public = $_ENV['PASSPORT_PUBLIC_KEY'] ?? $_SERVER['PASSPORT_PUBLIC_KEY'] ?? getenv('PASSPORT_PUBLIC_KEY') ?: null;

        return [
            is_string($private) && $private !== '' ? $private : null,
            is_string($public) && $public !== '' ? $public : null,
        ];
    } catch (Throwable) {
        return [null, null];
    }
}

function writeStorageKeys(string $privatePath, string $publicPath, string $private, string $public): void
{
    $dir = dirname($privatePath);
    if (! is_dir($dir) && ! mkdir($dir, 0755, true) && ! is_dir($dir)) {
        throw new RuntimeException("Cannot create storage directory: {$dir}");
    }

    if (file_put_contents($privatePath, $private) === false) {
        throw new RuntimeException("Failed to write {$privatePath}");
    }
    if (file_put_contents($publicPath, $public) === false) {
        throw new RuntimeException("Failed to write {$publicPath}");
    }

    // league/oauth2-server CryptKey rejects world-readable keys (e.g. 0644).
    hardenPassportKeyPermissions($privatePath, $publicPath);
}

/**
 * Passport/League require oauth key files to be 0600 or 0660 — not 0644.
 * A 0644 public key makes every throttled API route return HTTP 500.
 */
function hardenPassportKeyPermissions(string $privatePath, string $publicPath): void
{
    foreach ([$privatePath, $publicPath] as $path) {
        if (! is_file($path)) {
            continue;
        }
        @chmod($path, 0660);
    }
}

if (storageKeysValid($privatePath, $publicPath)) {
    hardenPassportKeyPermissions($privatePath, $publicPath);
    echo "Passport keys OK (storage volume).\n";
    exit(0);
}

$privateEnv = env('PASSPORT_PRIVATE_KEY');
$publicEnv = env('PASSPORT_PUBLIC_KEY');

if ((! is_string($privateEnv) || $privateEnv === '') || (! is_string($publicEnv) || $publicEnv === '')) {
    [$privateEnv, $publicEnv] = passportKeysFromEnvFile($root.'/.env');
}

if (is_string($privateEnv) && $privateEnv !== '' && is_string($publicEnv) && $publicEnv !== '') {
    $private = normalizePem($privateEnv);
    $public = normalizePem($publicEnv);

    if (! isValidPrivatePem($private) || ! isValidPublicPem($public)) {
        fwrite(STDERR, "ERROR: PASSPORT_*_KEY in .env are present but not valid PEM keys.\n");
        exit(1);
    }

    writeStorageKeys($privatePath, $publicPath, $private, $public);
    echo "Passport keys materialized from .env into storage volume.\n";
    exit(0);
}

fwrite(STDERR, "ERROR: Passport keys missing.\n");
fwrite(STDERR, "Add PASSPORT_PRIVATE_KEY and PASSPORT_PUBLIC_KEY to {$root}/.env,\n");
fwrite(STDERR, "set DURPALLA_PASSPORT_KEYS_DIR to the main app storage/ (oauth-*.key),\n");
fwrite(STDERR, "or place oauth-private.key / oauth-public.key in the apigw-storage volume.\n");
exit(1);
