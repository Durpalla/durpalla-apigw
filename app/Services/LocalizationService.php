<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class LocalizationService
{
    private const CACHE_TTL_SECONDS = 86400;

    /**
     * @return array<string, mixed>
     */
    public function metadata(): array
    {
        $bundled = config('localization.bundled_locales', ['en', 'bn']);
        $remote = config('localization.remote_locales', []);
        $locales = config('localization.locales', []);

        $remoteMeta = [];
        foreach ($remote as $code) {
            if (! isset($locales[$code])) {
                continue;
            }
            $remoteMeta[] = [
                'code' => $code,
                'name' => $locales[$code]['name'],
                'native_name' => $locales[$code]['native_name'],
                'direction' => $locales[$code]['direction'],
                'version' => $this->versionFor($code),
            ];
        }

        return [
            'default_locale' => config('localization.default_locale', 'en'),
            'bundled_locales' => $bundled,
            'remote_locales' => $remoteMeta,
        ];
    }

    /**
     * @return array{locale: string, version: int, fallback_locale: string, translations: array<string, mixed>, etag: string}
     */
    public function dictionary(string $locale): array
    {
        $normalized = strtolower(trim($locale));
        if (! in_array($normalized, array_keys(config('localization.locales', [])), true)) {
            throw new NotFoundHttpException('Locale not supported.');
        }

        $path = $this->pathFor($normalized);
        if (! File::exists($path)) {
            throw new NotFoundHttpException('Locale not found.');
        }

        $raw = File::get($path);
        $decoded = json_decode($raw, true);
        if (! is_array($decoded)) {
            throw new NotFoundHttpException('Locale data invalid.');
        }

        $translations = $decoded['translations'] ?? $decoded;
        if (! is_array($translations)) {
            $translations = [];
        }

        $version = (int) ($decoded['version'] ?? $this->versionFor($normalized));
        $etag = $this->etagFor($normalized, $raw);

        return [
            'locale' => $normalized,
            'version' => $version,
            'fallback_locale' => config('localization.default_locale', 'en'),
            'translations' => $translations,
            'etag' => $etag,
        ];
    }

    public function versionFor(string $locale): int
    {
        $path = $this->pathFor($locale);
        if (! File::exists($path)) {
            return 1;
        }

        $raw = File::get($path);
        $decoded = json_decode($raw, true);
        if (is_array($decoded) && isset($decoded['version'])) {
            return (int) $decoded['version'];
        }

        return (int) sprintf('%u', crc32($raw));
    }

    public function etagFor(string $locale, ?string $raw = null): string
    {
        $raw ??= File::exists($this->pathFor($locale)) ? File::get($this->pathFor($locale)) : '';

        return '"loc-'.$locale.'-'.hash('xxh128', $raw).'"';
    }

    /**
     * @return array<string, mixed>|null
     */
    public function cachedDictionary(string $locale): ?array
    {
        return Cache::remember(
            'localization:'.$locale,
            self::CACHE_TTL_SECONDS,
            fn () => $this->dictionary($locale)
        );
    }

    public function flushCache(?string $locale = null): void
    {
        if ($locale !== null) {
            Cache::forget('localization:'.$locale);

            return;
        }

        foreach (array_keys(config('localization.locales', [])) as $code) {
            Cache::forget('localization:'.$code);
        }
    }

    private function pathFor(string $locale): string
    {
        return resource_path('localizations/'.$locale.'.json');
    }
}
