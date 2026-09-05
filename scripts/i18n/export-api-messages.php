#!/usr/bin/env php
<?php

/**
 * Collect unique __('…') / trans('…') string keys from app/ for lang/en.json inventory.
 *
 * Usage: php scripts/i18n/export-api-messages.php
 */

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$scanDirs = [
    $root.'/app/Http',
    $root.'/app/Requests',
    $root.'/app/Exceptions',
];

$pattern = '/(?:__|trans)\(\s*[\'"]([^\'"]+)[\'"]/';

$keys = [];
foreach ($scanDirs as $dir) {
    if (! is_dir($dir)) {
        continue;
    }
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($iterator as $file) {
        if (! $file->isFile() || $file->getExtension() !== 'php') {
            continue;
        }
        $content = file_get_contents($file->getPathname());
        if (preg_match_all($pattern, $content, $matches)) {
            foreach ($matches[1] as $key) {
                $keys[$key] = $key;
            }
        }
    }
}

ksort($keys);

$langDir = $root.'/lang';
if (! is_dir($langDir)) {
    mkdir($langDir, 0775, true);
}

$enPath = $langDir.'/en.json';
$existing = [];
if (is_file($enPath)) {
    $existing = json_decode(file_get_contents($enPath), true) ?: [];
}

$en = array_merge($keys, $existing);
ksort($en);
file_put_contents(
    $enPath,
    json_encode($en, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n"
);

$allLocales = ['bn', 'hi', 'ar', 'zh', 'ur', 'fa', 'tr', 'es', 'it'];
foreach ($allLocales as $locale) {
    $path = $langDir.'/'.$locale.'.json';
    $current = [];
    if (is_file($path)) {
        $current = json_decode(file_get_contents($path), true) ?: [];
    }
    foreach ($en as $key => $english) {
        if (! isset($current[$key])) {
            $current[$key] = $locale === 'bn' && isset($existing[$key]) && $existing[$key] !== $key
                ? $existing[$key]
                : $english;
        }
    }
    ksort($current);
    file_put_contents(
        $path,
        json_encode($current, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n"
    );
}

echo 'API message keys: '.count($en).PHP_EOL;
