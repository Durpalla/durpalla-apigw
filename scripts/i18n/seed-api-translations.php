#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Translate lang/en.json into all supported API locales.
 */

require __DIR__.'/lib/translate.php';

$root = dirname(__DIR__, 2);
$enPath = $root.'/lang/en.json';
$en = json_decode(file_get_contents($enPath), true, 512, JSON_THROW_ON_ERROR);

$targets = [
    'bn' => 'bn',
    'hi' => 'hi',
    'ar' => 'ar',
    'zh' => 'zh-CN',
    'ur' => 'ur',
    'fa' => 'fa',
    'tr' => 'tr',
    'es' => 'es',
    'it' => 'it',
];

foreach ($targets as $fileLocale => $googleTarget) {
    $outPath = $root.'/lang/'.$fileLocale.'.json';
    $existing = is_file($outPath)
        ? json_decode(file_get_contents($outPath), true) ?: []
        : [];

    $alreadyDone = count($en) > 0 && count(array_filter(
        $en,
        static fn (string $key): bool => isset($existing[$key]) && $existing[$key] !== $key
    )) >= count($en);

    if ($alreadyDone) {
        echo "API -> {$fileLocale} skipped (already translated)\n";
        continue;
    }

    echo "API -> {$fileLocale} (".count($en)." keys)\n";
    $out = [];
    $i = 0;
    foreach ($en as $key => $_) {
        ++$i;
        if (
            $fileLocale === 'bn'
            && isset($existing[$key])
            && $existing[$key] !== $key
            && mb_strlen($existing[$key]) > 0
        ) {
            $out[$key] = $existing[$key];
            continue;
        }
        $out[$key] = i18n_translate_en($key, $googleTarget);
        if ($i % 30 === 0) {
            echo "  {$i}/".count($en)."\n";
        }
    }

    ksort($out);
    file_put_contents(
        $outPath,
        json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n"
    );
    echo "  wrote {$outPath}\n";
}

echo "API lang/*.json complete.\n";
