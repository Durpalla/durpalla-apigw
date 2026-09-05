#!/usr/bin/env php
<?php

/**
 * Validate app-specific localization resources.
 *
 * Usage: php scripts/i18n/validate-localizations.php
 */

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$base = $root.'/resources/localizations';
$apps = require $root.'/config/localization.php';
$apps = $apps['apps'] ?? [];
$allLocales = array_keys($apps ? (require $root.'/config/localization.php')['locales'] : []);

$config = require $root.'/config/localization.php';
$allLocales = array_keys($config['locales']);
$errors = [];

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

foreach (array_keys($apps) as $app) {
    $format = $apps[$app]['format'] ?? 'arb-flat';
    $enDir = $base.'/'.$app.'/en';
    if (! is_dir($enDir)) {
        $errors[] = "Missing English export: {$app}/en";
        continue;
    }

    if ($format === 'arb-flat') {
        $enPath = $enDir.'/messages.json';
        if (! is_file($enPath)) {
            $errors[] = "Missing {$app}/en/messages.json";
            continue;
        }
        $en = json_decode(file_get_contents($enPath), true);
        $enKeys = array_keys($en ?: []);
    } else {
        $enKeys = [];
        $nsFiles = glob($enDir.'/*.json') ?: [];
        foreach ($nsFiles as $file) {
            if (basename($file) === 'manifest.json') {
                continue;
            }
            $data = json_decode(file_get_contents($file), true);
            $enKeys = array_merge($enKeys, array_keys(flattenKeys(is_array($data) ? $data : [])));
        }
    }

    foreach ($allLocales as $locale) {
        $localeDir = $base.'/'.$app.'/'.$locale;
        if (! is_dir($localeDir)) {
            $errors[] = "Missing locale dir: {$app}/{$locale}";
            continue;
        }
        if (! is_file($localeDir.'/manifest.json')) {
            $errors[] = "Missing manifest: {$app}/{$locale}/manifest.json";
        }

        if ($format === 'arb-flat') {
            $path = $localeDir.'/messages.json';
            if (! is_file($path)) {
                $errors[] = "Missing {$app}/{$locale}/messages.json";
                continue;
            }
            $data = json_decode(file_get_contents($path), true);
            $keys = array_keys(is_array($data) ? $data : []);
            $missing = array_diff($enKeys, $keys);
            if ($missing !== []) {
                $errors[] = "{$app}/{$locale} missing ".count($missing).' keys';
            }
        } else {
            foreach (glob($enDir.'/*.json') ?: [] as $enFile) {
                $name = basename($enFile);
                if ($name === 'manifest.json') {
                    continue;
                }
                $target = $localeDir.'/'.$name;
                if (! is_file($target)) {
                    $errors[] = "Missing {$app}/{$locale}/{$name}";
                }
            }
        }
    }
}

if ($errors !== []) {
    fwrite(STDERR, implode(PHP_EOL, $errors).PHP_EOL);
    exit(1);
}

echo 'Localization validation passed ('.count($apps).' apps).'.PHP_EOL;
