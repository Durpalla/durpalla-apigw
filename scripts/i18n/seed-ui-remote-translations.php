#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Translate remote UI files from English exports (hi, ar, zh, ur, fa, tr, es, it).
 */

require __DIR__.'/lib/translate.php';

$root = dirname(__DIR__, 2);
$base = $root.'/resources/localizations';
$remote = ['hi', 'ar', 'zh', 'ur', 'fa', 'tr', 'es', 'it'];
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

function translateTree(mixed $node, string $target): mixed
{
    if (is_array($node)) {
        $out = [];
        foreach ($node as $k => $v) {
            $out[$k] = translateTree($v, $target);
        }

        return $out;
    }

    if (is_string($node)) {
        if ($node === '' || ! preg_match('/[A-Za-z]/', $node)) {
            return $node;
        }

        return i18n_translate_en($node, $target);
    }

    return $node;
}

function translateFlatFile(string $enPath, string $outPath, string $target): void
{
    $data = json_decode(file_get_contents($enPath), true, 512, JSON_THROW_ON_ERROR);
    $out = [];
    $total = count($data);
    $i = 0;
    foreach ($data as $key => $value) {
        ++$i;
        $out[$key] = is_string($value) ? i18n_translate_en($value, $target) : $value;
        if ($i % 150 === 0) {
            echo "    {$i}/{$total}\n";
        }
    }
    file_put_contents(
        $outPath,
        json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n"
    );
}

function flatTranslatedRatio(string $enPath, string $outPath): float
{
    if (! is_file($outPath)) {
        return 0.0;
    }
    $en = json_decode(file_get_contents($enPath), true, 512, JSON_THROW_ON_ERROR);
    $out = json_decode(file_get_contents($outPath), true, 512, JSON_THROW_ON_ERROR);
    if (! is_array($en) || $en === []) {
        return 1.0;
    }
    $translated = 0;
    foreach ($en as $key => $value) {
        if (! is_string($value)) {
            continue;
        }
        if (($out[$key] ?? null) !== $value) {
            ++$translated;
        }
    }

    return $translated / count($en);
}

function namespaceTranslatedRatio(string $enDir, string $outDir): float
{
    $total = 0;
    $translated = 0;
    foreach (glob($enDir.'/*.json') ?: [] as $enNs) {
        if (basename($enNs) === 'manifest.json') {
            continue;
        }
        $enRaw = json_decode(file_get_contents($enNs), true, 512, JSON_THROW_ON_ERROR);
        $outPath = $outDir.'/'.basename($enNs);
        if (! is_file($outPath)) {
            return 0.0;
        }
        $outRaw = json_decode(file_get_contents($outPath), true, 512, JSON_THROW_ON_ERROR);
        $walk = static function (mixed $enNode, mixed $outNode) use (&$walk, &$total, &$translated): void {
            if (! is_array($enNode)) {
                return;
            }
            foreach ($enNode as $key => $value) {
                if (is_array($value)) {
                    $walk($value, is_array($outNode) ? ($outNode[$key] ?? []) : []);
                    continue;
                }
                if (! is_string($value)) {
                    continue;
                }
                ++$total;
                $outValue = is_array($outNode) ? ($outNode[$key] ?? null) : null;
                if ($outValue !== $value) {
                    ++$translated;
                }
            }
        };
        $walk($enRaw, $outRaw);
    }

    return $total === 0 ? 1.0 : $translated / $total;
}

foreach (['customer-app', 'merchant-desk'] as $app) {
    $enFile = "{$base}/{$app}/en/messages.json";
    if (! is_file($enFile)) {
        continue;
    }
    foreach ($remote as $locale) {
        $google = $targets[$locale];
        $outFile = "{$base}/{$app}/{$locale}/messages.json";
        if (flatTranslatedRatio($enFile, $outFile) >= 0.9) {
            echo "{$app}/{$locale} skipped (already translated)\n";
            continue;
        }
        echo "{$app}/{$locale}\n";
        translateFlatFile($enFile, $outFile, $google);
    }
}

foreach (['web-merchant'] as $app) {
    $enDir = "{$base}/{$app}/en";
    foreach ($remote as $locale) {
        $google = $targets[$locale];
        $outDir = "{$base}/{$app}/{$locale}";
        if (! is_dir($outDir)) {
            mkdir($outDir, 0775, true);
        }
        if (namespaceTranslatedRatio($enDir, $outDir) >= 0.9) {
            echo "{$app}/{$locale} skipped (already translated)\n";
            continue;
        }
        foreach (glob($enDir.'/*.json') ?: [] as $enNs) {
            if (basename($enNs) === 'manifest.json') {
                continue;
            }
            $raw = json_decode(file_get_contents($enNs), true, 512, JSON_THROW_ON_ERROR);
            $translated = translateTree($raw, $google);
            file_put_contents(
                $outDir.'/'.basename($enNs),
                json_encode($translated, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n"
            );
        }
        echo "{$app}/{$locale} namespaces\n";
    }
}

echo "Remote UI translations complete.\n";
