#!/usr/bin/env php
<?php

/**
 * Export durpalla-web lib/i18n/messages.ts → resources/localizations/web-customer/{locale}/common.json
 *
 * Usage: php scripts/i18n/export-web-customer.php
 */

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$outBase = $root.'/resources/localizations';
$allLocales = ['en', 'bn', 'hi', 'ar', 'zh', 'ur', 'fa', 'tr', 'es', 'it'];
$messagesTs = getenv('WEB_CUSTOMER_MESSAGES_TS') ?: '/var/www/html/durpalla-web/lib/i18n/messages.ts';

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

function writeManifest(string $app, string $locale, int $keyCount, int $version = 1): void
{
    global $outBase;
    writeJson($outBase.'/'.$app.'/'.$locale.'/manifest.json', [
        'app' => $app,
        'locale' => $locale,
        'version' => $version,
        'fallback_locale' => 'en',
        'format' => 'i18next-namespaces',
        'key_count' => $keyCount,
        'exported_at' => gmdate('c'),
    ]);
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
                $flat[stripcslashes($pair[1])] = stripcslashes($pair[2]);
            }
        }
        // Double-quoted values: 'key': "value"
        if (preg_match_all("/'((?:\\\\'|[^'])*)':\\s*\"((?:\\\\\"|[^\"])*)\"/", $match[2], $pairs, PREG_SET_ORDER)) {
            foreach ($pairs as $pair) {
                $flat[stripcslashes($pair[1])] = stripcslashes($pair[2]);
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

$packs = parseWebCustomerMessagesTs($messagesTs);
if (! isset($packs['en'])) {
    throw new RuntimeException('enMessages missing from messages.ts');
}
$enFlat = $packs['en'];
$app = 'web-customer';

foreach ($allLocales as $locale) {
    $flat = $packs[$locale] ?? [];
    foreach ($enFlat as $key => $enValue) {
        if (! array_key_exists($key, $flat)) {
            $flat[$key] = $enValue;
        }
    }
    ksort($flat);
    $nested = nestFlatMessages($flat);
    writeJson($outBase.'/'.$app.'/'.$locale.'/common.json', $nested);
    writeManifest($app, $locale, count($flat), 2);
    echo sprintf("  %s: %d keys\n", $locale, count($flat));
}

echo 'Exported web-customer from messages.ts ('.count($enFlat)." en keys)\n";
