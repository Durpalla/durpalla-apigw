#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Translate web-merchant remote locales by unique English strings (resumable).
 *
 * Usage:
 *   php scripts/i18n/seed-web-merchant-unique.php
 *   php scripts/i18n/seed-web-merchant-unique.php tr,ar
 */

require __DIR__.'/lib/translate.php';

$root = dirname(__DIR__, 2);
$base = $root.'/resources/localizations/web-merchant';
$enDir = $base.'/en';
$allRemote = ['hi', 'ar', 'zh', 'ur', 'fa', 'tr', 'es', 'it'];
$targets = [
    'hi' => 'hi',
    'ar' => 'ar',
    'zh' => 'zh-CN',
    'ur' => 'ur',
    'fa' => 'fa',
    'tr' => 'tr',
    'es' => 'es',
    'it' => 'it',
];

$arg = $argv[1] ?? null;
$remote = $arg !== null
    ? array_values(array_filter(array_map('trim', explode(',', $arg))))
    : $allRemote;

function collectStrings(mixed $node, array &$out): void
{
    if (is_array($node)) {
        foreach ($node as $value) {
            collectStrings($value, $out);
        }

        return;
    }
    if (is_string($node) && $node !== '' && preg_match('/[A-Za-z]/', $node)) {
        $out[$node] = true;
    }
}

function applyMap(mixed $node, array $map): mixed
{
    if (is_array($node)) {
        $out = [];
        foreach ($node as $key => $value) {
            $out[$key] = applyMap($value, $map);
        }

        return $out;
    }
    if (is_string($node) && isset($map[$node])) {
        return $map[$node];
    }

    return $node;
}

function leafDiffRatio(mixed $enNode, mixed $outNode, int &$total, int &$translated): void
{
    if (is_array($enNode)) {
        foreach ($enNode as $key => $value) {
            leafDiffRatio($value, is_array($outNode) ? ($outNode[$key] ?? null) : null, $total, $translated);
        }

        return;
    }
    if (! is_string($enNode)) {
        return;
    }
    ++$total;
    if ($outNode !== $enNode) {
        ++$translated;
    }
}

$unique = [];
foreach (glob($enDir.'/*.json') ?: [] as $file) {
    if (basename($file) === 'manifest.json') {
        continue;
    }
    $raw = json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
    collectStrings($raw, $unique);
}
$strings = array_keys($unique);
sort($strings);
$totalUnique = count($strings);
echo "Unique English strings: {$totalUnique}\n";

foreach ($remote as $locale) {
    if (! isset($targets[$locale])) {
        echo "Skip unknown locale {$locale}\n";
        continue;
    }
    $google = $targets[$locale];
    $outDir = $base.'/'.$locale;
    if (! is_dir($outDir)) {
        mkdir($outDir, 0775, true);
    }

    $map = [];
    $done = 0;
    $miss = 0;
    foreach ($strings as $text) {
        ++$done;
        $translated = i18n_translate_en($text, $google);
        $map[$text] = $translated;
        if ($translated === $text) {
            ++$miss;
        }
        if ($done % 100 === 0 || $done === $totalUnique) {
            echo "{$locale} strings {$done}/{$totalUnique} (unchanged {$miss})\n";
        }
    }

    $translatedUnique = $totalUnique - $miss;
    $uniqueRatio = $totalUnique === 0 ? 1.0 : $translatedUnique / $totalUnique;
    if ($uniqueRatio < 0.7) {
        echo "{$locale} SKIP write (unique ratio=".round($uniqueRatio, 3)." < 0.7) — retry later\n";
        continue;
    }

    foreach (glob($enDir.'/*.json') ?: [] as $enNs) {
        $name = basename($enNs);
        if ($name === 'manifest.json') {
            continue;
        }
        $raw = json_decode(file_get_contents($enNs), true, 512, JSON_THROW_ON_ERROR);
        $translatedTree = applyMap($raw, $map);
        file_put_contents(
            $outDir.'/'.$name,
            json_encode($translatedTree, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n"
        );
    }

    $manifestPath = $outDir.'/manifest.json';
    $total = 0;
    $translatedCount = 0;
    foreach (glob($enDir.'/*.json') ?: [] as $enNs) {
        if (basename($enNs) === 'manifest.json') {
            continue;
        }
        $enRaw = json_decode(file_get_contents($enNs), true, 512, JSON_THROW_ON_ERROR);
        $outRaw = json_decode(file_get_contents($outDir.'/'.basename($enNs)), true, 512, JSON_THROW_ON_ERROR);
        leafDiffRatio($enRaw, $outRaw, $total, $translatedCount);
    }
    $ratio = $total === 0 ? 1.0 : $translatedCount / $total;

    $manifest = is_file($manifestPath)
        ? json_decode(file_get_contents($manifestPath), true, 512, JSON_THROW_ON_ERROR)
        : ['app' => 'web-merchant', 'locale' => $locale, 'fallback_locale' => 'en', 'format' => 'i18next-namespaces'];
    $manifest['version'] = ((int) ($manifest['version'] ?? 1)) + 1;
    $manifest['key_count'] = $total;
    $manifest['exported_at'] = gmdate('c');
    file_put_contents(
        $manifestPath,
        json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n"
    );

    echo "{$locale} done ratio=".round($ratio, 3)." version={$manifest['version']}\n";
}

echo "web-merchant unique-string seed complete.\n";
