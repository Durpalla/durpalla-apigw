<?php

declare(strict_types=1);

/**
 * Shared EN → target translation for i18n seed scripts.
 * Primary: Google dict-chrome-ex (no API key). Fallback: MyMemory.
 * Persists cache to scripts/i18n/.translate-cache.json between runs.
 */

function i18n_translate_cache_path(): string
{
    return dirname(__DIR__).'/.translate-cache.json';
}

/** @var array<string, string> */
$GLOBALS['i18n_translate_cache'] ??= [];
$GLOBALS['i18n_translate_cache_writes'] ??= 0;

function i18n_translate_load_cache(): void
{
    $path = i18n_translate_cache_path();
    if (! is_file($path)) {
        return;
    }
    $decoded = json_decode(file_get_contents($path), true);
    if (is_array($decoded)) {
        $GLOBALS['i18n_translate_cache'] = $decoded;
    }
}

function i18n_translate_save_cache(): void
{
    $path = i18n_translate_cache_path();
    file_put_contents(
        $path,
        json_encode($GLOBALS['i18n_translate_cache'], JSON_UNESCAPED_UNICODE)."\n"
    );
}

function i18n_translate_via_google(string $text, string $target): ?string
{
    $url = 'https://clients5.google.com/translate_a/t?'.http_build_query([
        'client' => 'dict-chrome-ex',
        'sl' => 'en',
        'tl' => $target,
        'q' => $text,
    ]);

    $ch = curl_init($url);
    if ($ch === false) {
        return null;
    }

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 25,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
    ]);

    $response = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if (! is_string($response) || $code !== 200 || $response === '') {
        return null;
    }

    $decoded = json_decode($response, true);
    if (is_array($decoded) && isset($decoded[0]) && is_string($decoded[0]) && $decoded[0] !== '') {
        return $decoded[0];
    }

    return null;
}

function i18n_translate_via_mymemory(string $text, string $target): ?string
{
    $pair = 'en|'.($target === 'zh-CN' ? 'zh' : $target);
    $url = 'https://api.mymemory.translated.net/get?'.http_build_query([
        'q' => $text,
        'langpair' => $pair,
    ]);

    $ch = curl_init($url);
    if ($ch === false) {
        return null;
    }

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 25,
        CURLOPT_USERAGENT => 'Durpalla-Localization/1.0',
    ]);

    $response = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if (! is_string($response) || $code !== 200) {
        return null;
    }

    $decoded = json_decode($response, true);
    if (! is_array($decoded) || ($decoded['quotaFinished'] ?? false)) {
        return null;
    }

    $translated = $decoded['responseData']['translatedText'] ?? null;

    return is_string($translated) && $translated !== '' ? $translated : null;
}

function i18n_translate_en(string $text, string $target): string
{
    if ($text === '') {
        return $text;
    }

    $cacheKey = $target.'|'.$text;
    if (isset($GLOBALS['i18n_translate_cache'][$cacheKey])) {
        return $GLOBALS['i18n_translate_cache'][$cacheKey];
    }

    $translated = i18n_translate_via_google($text, $target)
        ?? i18n_translate_via_mymemory($text, $target)
        ?? $text;

    $GLOBALS['i18n_translate_cache'][$cacheKey] = $translated;
    if ((++$GLOBALS['i18n_translate_cache_writes'] % 50) === 0) {
        i18n_translate_save_cache();
    }
    usleep(90000);

    return $translated;
}

i18n_translate_load_cache();

register_shutdown_function(static function (): void {
    i18n_translate_save_cache();
});
