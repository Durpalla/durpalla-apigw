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

function parseWebCustomerMessagesTs(string $messagesTsPath): array
{
    if (! is_file($messagesTsPath)) {
        throw new RuntimeException("web-customer messages.ts not found: {$messagesTsPath}");
    }
    $src = file_get_contents($messagesTsPath);
    $packs = [];
    if (! preg_match_all(
        "/export const ([a-z]{2})Messages: Record<string, string> = \\{([\\s\\S]*?)\\n\\}/",
        $src,
        $matches,
        PREG_SET_ORDER
    )) {
        throw new RuntimeException('No locale message blocks found in messages.ts');
    }
    foreach ($matches as $match) {
        $locale = $match[1];
        $flat = [];
        // Single-quoted values: 'key': 'value'
        if (preg_match_all("/'((?:\\\\'|[^'])*)':\\s*'((?:\\\\'|[^'])*)'/", $match[2], $pairs, PREG_SET_ORDER)) {
            foreach ($pairs as $pair) {
                $key = stripcslashes($pair[1]);
                $value = stripcslashes($pair[2]);
                $flat[$key] = $value;
            }
        }
        // Double-quoted values: 'key': "value"
        if (preg_match_all("/'((?:\\\\'|[^'])*)':\\s*\"((?:\\\\\"|[^\"])*)\"/", $match[2], $pairs, PREG_SET_ORDER)) {
            foreach ($pairs as $pair) {
                $key = stripcslashes($pair[1]);
                $value = stripcslashes($pair[2]);
                $flat[$key] = $value;
            }
        }
        $packs[$locale] = $flat;
    }

    return $packs;
}

function nestFlatMessages(array $flat): array
{
    $root = [];
    foreach ($flat as $full => $value) {
        $parts = explode('.', (string) $full);
        $cur = &$root;
        $last = array_pop($parts);
        foreach ($parts as $part) {
            if (! isset($cur[$part]) || ! is_array($cur[$part])) {
                $cur[$part] = [];
            }
            $cur = &$cur[$part];
        }
        $cur[$last] = $value;
        unset($cur);
    }

    return $root;
}

function exportWebCustomer(string $app): void
{
    global $outBase, $allLocales;

    $messagesTs = '/var/www/html/durpalla-web/lib/i18n/messages.ts';
    $packs = parseWebCustomerMessagesTs($messagesTs);
    if (! isset($packs['en'])) {
        throw new RuntimeException('enMessages missing from messages.ts');
    }
    $enFlat = $packs['en'];

    foreach ($allLocales as $locale) {
        $flat = $packs[$locale] ?? [];
        foreach ($enFlat as $key => $enValue) {
            if (! array_key_exists($key, $flat)) {
                $flat[$key] = $enValue;
            }
        }
        $nested = nestFlatMessages($flat);
        writeJson($outBase.'/'.$app.'/'.$locale.'/common.json', $nested);
        writeManifest($app, $locale, 'i18next-namespaces', count($flat), 2);
    }

    echo 'Exported web-customer namespaces from messages.ts ('.count($enFlat)." keys)\n";
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
