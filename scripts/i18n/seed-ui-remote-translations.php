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

foreach (['customer-app', 'merchant-desk'] as $app) {
    $enFile = "{$base}/{$app}/en/messages.json";
    if (! is_file($enFile)) {
        continue;
    }
    foreach ($remote as $locale) {
        $google = $targets[$locale];
        $outFile = "{$base}/{$app}/{$locale}/messages.json";
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
