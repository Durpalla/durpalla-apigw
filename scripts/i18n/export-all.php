#!/usr/bin/env php
<?php

/**
 * Export UI localizations from client repos into resources/localizations/{app}/{locale}/.
 *
 * Usage: php scripts/i18n/export-all.php
 */

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$outBase = $root.'/resources/localizations';
$allLocales = array_merge(['en', 'bn'], ['hi', 'ar', 'zh', 'ur', 'fa', 'tr', 'es', 'it']);

function writeJson(string $path, array $data): void
{
    $dir = dirname($path);
    if (! is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    file_put_contents(
        $path,
        json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n"
    );
}

function arbToFlat(string $arbPath): array
{
    if (! is_file($arbPath)) {
        throw new RuntimeException("ARB not found: {$arbPath}");
    }
    $decoded = json_decode(file_get_contents($arbPath), true, 512, JSON_THROW_ON_ERROR);
    $flat = [];
    foreach ($decoded as $key => $value) {
        if (str_starts_with($key, '@')) {
            continue;
        }
        if (is_string($value)) {
            $flat[$key] = $value;
        }
    }

    return $flat;
}

function writeManifest(string $app, string $locale, string $format, int $keyCount, int $version = 1): void
{
    global $outBase;
    writeJson($outBase.'/'.$app.'/'.$locale.'/manifest.json', [
        'app' => $app,
        'locale' => $locale,
        'version' => $version,
        'fallback_locale' => 'en',
        'format' => $format,
        'key_count' => $keyCount,
        'exported_at' => gmdate('c'),
    ]);
}

function exportArbApp(string $app, string $enArb, string $bnArb): void
{
    global $outBase, $allLocales;

    $en = arbToFlat($enArb);
    $bn = arbToFlat($bnArb);

    writeJson($outBase.'/'.$app.'/en/messages.json', $en);
    writeManifest($app, 'en', 'arb-flat', count($en));
    writeJson($outBase.'/'.$app.'/bn/messages.json', $bn);
    writeManifest($app, 'bn', 'arb-flat', count($bn));

    foreach ($allLocales as $locale) {
        if (in_array($locale, ['en', 'bn'], true)) {
            continue;
        }
        $target = $outBase.'/'.$app.'/'.$locale.'/messages.json';
        if (! is_file($target)) {
            writeJson($target, $en);
            writeManifest($app, $locale, 'arb-flat', count($en));
        }
    }

    echo "Exported arb-flat: {$app}\n";
}

function copyNamespaceDir(string $app, string $sourceLngDir): void
{
    global $outBase, $allLocales;

    if (! is_dir($sourceLngDir)) {
        throw new RuntimeException("Missing locale dir: {$sourceLngDir}");
    }

    $files = glob($sourceLngDir.'/*.json') ?: [];
    $keyCount = 0;
    foreach (['en', 'bn'] as $locale) {
        $localeDir = dirname($sourceLngDir).'/'.$locale;
        if (! is_dir($localeDir)) {
            continue;
        }
        foreach (glob($localeDir.'/*.json') ?: [] as $file) {
            $name = basename($file);
            $data = json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
            writeJson($outBase.'/'.$app.'/'.$locale.'/'.$name, $data);
            if ($locale === 'en') {
                $keyCount += count(flattenKeys($data));
            }
        }
        writeManifest($app, $locale, 'i18next-namespaces', max($keyCount, 1));
    }

    $enDir = $outBase.'/'.$app.'/en';
    foreach ($allLocales as $locale) {
        if (in_array($locale, ['en', 'bn'], true)) {
            continue;
        }
        $targetLocaleDir = $outBase.'/'.$app.'/'.$locale;
        if (! is_dir($targetLocaleDir)) {
            mkdir($targetLocaleDir, 0775, true);
        }
        foreach (glob($enDir.'/*.json') ?: [] as $enFile) {
            if (basename($enFile) === 'manifest.json') {
                continue;
            }
            $name = basename($enFile);
            $target = $targetLocaleDir.'/'.$name;
            if (! is_file($target)) {
                copy($enFile, $target);
            }
        }
        if (! is_file($targetLocaleDir.'/manifest.json')) {
            writeManifest($app, $locale, 'i18next-namespaces', max($keyCount, 1));
        }
    }

    echo "Exported i18next: {$app}\n";
}

function flattenKeys(array $data, string $prefix = ''): array
{
    $out = [];
    foreach ($data as $key => $value) {
        $full = $prefix === '' ? (string) $key : $prefix.'.'.$key;
        if (is_array($value)) {
            $out = array_merge($out, flattenKeys($value, $full));
        } elseif (is_string($value)) {
            $out[$full] = $value;
        }
    }

    return $out;
}

function exportWebCustomer(string $app): void
{
    global $outBase, $allLocales;

    $en = [
        'nav' => [
            'bus' => 'Bus',
            'launch' => 'Launch',
            'boat' => 'Boat',
            'hotels' => 'Hotels',
        ],
        'language' => [
            'label' => 'Language',
            'switch' => 'Change language',
        ],
    ];
    $bn = [
        'nav' => [
            'bus' => 'বাস',
            'launch' => 'লঞ্চ',
            'boat' => 'নৌকা',
            'hotels' => 'হোটেল',
        ],
        'language' => [
            'label' => 'ভাষা',
            'switch' => 'ভাষা পরিবর্তন',
        ],
    ];

    writeJson($outBase.'/'.$app.'/en/common.json', $en);
    writeJson($outBase.'/'.$app.'/bn/common.json', $bn);
    $keys = count(flattenKeys($en));
    writeManifest($app, 'en', 'i18next-namespaces', $keys);
    writeManifest($app, 'bn', 'i18next-namespaces', count(flattenKeys($bn)));

    foreach ($allLocales as $locale) {
        if (in_array($locale, ['en', 'bn'], true)) {
            continue;
        }
        $dir = $outBase.'/'.$app.'/'.$locale;
        if (! is_file($dir.'/common.json')) {
            writeJson($dir.'/common.json', $en);
            writeManifest($app, $locale, 'i18next-namespaces', $keys);
        }
    }

    echo "Exported web-customer namespaces\n";
}

// Flutter ARB exports
exportArbApp(
    'customer-app',
    '/var/www/html/durpalla-flutter-app/lib/l10n/app_en.arb',
    '/var/www/html/durpalla-flutter-app/lib/l10n/app_bn.arb'
);

exportArbApp(
    'merchant-desk',
    '/var/www/html/durpalla-flutter-merchant-desk/lib/l10n/app_en.arb',
    '/var/www/html/durpalla-flutter-merchant-desk/lib/l10n/app_bn.arb'
);

copyNamespaceDir(
    'web-merchant',
    '/var/www/html/durpalla-web-merchant/src/i18n/locales/en'
);

exportWebCustomer('web-customer');

// Remove legacy flat locale files
foreach (glob($outBase.'/*.json') ?: [] as $legacy) {
    unlink($legacy);
    echo 'Removed legacy '.basename($legacy)."\n";
}

echo "Done.\n";
