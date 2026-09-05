<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class LocalizationService
{
    private const CACHE_TTL_SECONDS = 86400;

    public function isValidApp(string $app): bool
    {
        return isset(config('localization.apps', [])[$app]);
    }

    public function isValidLocale(string $locale): bool
    {
        return isset(config('localization.locales', [])[$locale]);
    }

    public function appFormat(string $app): string
    {
        return (string) (config('localization.apps.'.$app.'.format') ?? 'arb-flat');
    }

    /**
     * @return array<string, mixed>
     */
    public function metadata(?string $app = null): array
    {
        if ($app !== null) {
            return $this->appMetadata($app);
        }

        $apps = [];
        foreach (array_keys(config('localization.apps', [])) as $code) {
            $apps[] = $this->appMetadata($code);
        }

        return [
            'default_locale' => config('localization.default_locale', 'en'),
            'apps' => $apps,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function appMetadata(string $app): array
    {
        $this->assertApp($app);
        $config = config('localization.apps.'.$app);
        $bundled = $config['bundled_locales'] ?? config('localization.bundled_locales', ['en', 'bn']);
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
                'version' => $this->versionFor($app, $code),
            ];
        }

        return [
            'code' => $app,
            'label' => $config['label'] ?? $app,
            'format' => $config['format'] ?? 'arb-flat',
            'bundled_locales' => $bundled,
            'remote_locales' => $remoteMeta,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function dictionary(string $app, string $locale, ?string $namespace = null, bool $combined = false): array
    {
        $this->assertApp($app);
        $locale = strtolower(trim($locale));
        $this->assertLocale($locale);

        $format = $this->appFormat($app);

        if ($format === 'arb-flat') {
            return $this->arbFlatDictionary($app, $locale);
        }

        if ($namespace !== null) {
            return $this->namespaceDictionary($app, $locale, $namespace);
        }

        return $this->combinedNamespacesDictionary($app, $locale);
    }

    /**
     * @return array<string, mixed>
     */
    public function cachedDictionary(string $app, string $locale, ?string $namespace = null, bool $combined = false): array
    {
        $cacheKey = sprintf(
            'localization:%s:%s:%s:%s',
            $app,
            $locale,
            $namespace ?? '-',
            $combined ? '1' : '0'
        );

        return Cache::remember(
            $cacheKey,
            self::CACHE_TTL_SECONDS,
            fn () => $this->dictionary($app, $locale, $namespace, $combined)
        );
    }

    public function versionFor(string $app, string $locale): int
    {
        $manifest = $this->readManifest($app, $locale);
        if ($manifest !== null && isset($manifest['version'])) {
            return (int) $manifest['version'];
        }

        $dir = $this->localeDir($app, $locale);
        if (! File::isDirectory($dir)) {
            return 1;
        }

        return (int) sprintf('%u', crc32(implode('', File::allFiles($dir))));
    }

    public function flushCache(?string $app = null, ?string $locale = null): void
    {
        if ($app === null) {
            foreach (array_keys(config('localization.apps', [])) as $code) {
                $this->flushCache($code);
            }

            return;
        }

        foreach (array_keys(config('localization.locales', [])) as $code) {
            if ($locale !== null && $code !== $locale) {
                continue;
            }
            Cache::forget(sprintf('localization:%s:%s:-:0', $app, $code));
            Cache::forget(sprintf('localization:%s:%s:-:1', $app, $code));
            foreach ($this->listNamespaces($app, $code) as $ns) {
                Cache::forget(sprintf('localization:%s:%s:%s:0', $app, $code, $ns));
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function arbFlatDictionary(string $app, string $locale): array
    {
        $path = $this->localeDir($app, $locale).'/messages.json';
        if (! File::exists($path)) {
            throw new NotFoundHttpException('Locale not found.');
        }

        $raw = File::get($path);
        $decoded = json_decode($raw, true);
        if (! is_array($decoded)) {
            throw new NotFoundHttpException('Locale data invalid.');
        }

        $manifest = $this->readManifest($app, $locale);
        $version = (int) ($manifest['version'] ?? $this->versionFor($app, $locale));
        $etag = $this->etagFor($app, $locale, $raw);

        return [
            'app' => $app,
            'locale' => $locale,
            'format' => 'arb-flat',
            'version' => $version,
            'fallback_locale' => config('localization.default_locale', 'en'),
            'translations' => $decoded,
            'etag' => $etag,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function namespaceDictionary(string $app, string $locale, string $namespace): array
    {
        $namespace = $this->sanitizeNamespace($namespace);
        $path = $this->localeDir($app, $locale).'/'.$namespace.'.json';
        if (! File::exists($path)) {
            throw new NotFoundHttpException('Namespace not found.');
        }

        $raw = File::get($path);
        $decoded = json_decode($raw, true);
        if (! is_array($decoded)) {
            throw new NotFoundHttpException('Namespace data invalid.');
        }

        $manifest = $this->readManifest($app, $locale);
        $etag = $this->etagFor($app, $locale, $raw, $namespace);

        return [
            'app' => $app,
            'locale' => $locale,
            'namespace' => $namespace,
            'format' => 'i18next-namespaces',
            'version' => (int) ($manifest['version'] ?? 1),
            'fallback_locale' => config('localization.default_locale', 'en'),
            'translations' => $decoded,
            'etag' => $etag,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function combinedNamespacesDictionary(string $app, string $locale): array
    {
        $dir = $this->localeDir($app, $locale);
        if (! File::isDirectory($dir)) {
            throw new NotFoundHttpException('Locale not found.');
        }

        $combined = [];
        $rawParts = [];
        foreach ($this->listNamespaces($app, $locale) as $namespace) {
            $path = $dir.'/'.$namespace.'.json';
            $raw = File::get($path);
            $rawParts[] = $raw;
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $combined[$namespace] = $decoded;
            }
        }

        if ($combined === []) {
            throw new NotFoundHttpException('Locale not found.');
        }

        $manifest = $this->readManifest($app, $locale);
        $etag = $this->etagFor($app, $locale, implode('', $rawParts), 'combined');

        return [
            'app' => $app,
            'locale' => $locale,
            'format' => 'i18next-namespaces',
            'version' => (int) ($manifest['version'] ?? 1),
            'fallback_locale' => config('localization.default_locale', 'en'),
            'translations' => $combined,
            'etag' => $etag,
        ];
    }

    /**
     * @return list<string>
     */
    public function listNamespaces(string $app, string $locale): array
    {
        $dir = $this->localeDir($app, $locale);
        if (! File::isDirectory($dir)) {
            return [];
        }

        $namespaces = [];
        foreach (File::files($dir) as $file) {
            $name = $file->getFilename();
            if ($name === 'manifest.json' || ! str_ends_with($name, '.json')) {
                continue;
            }
            $namespaces[] = basename($name, '.json');
        }
        sort($namespaces);

        return $namespaces;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function readManifest(string $app, string $locale): ?array
    {
        $path = $this->localeDir($app, $locale).'/manifest.json';
        if (! File::exists($path)) {
            return null;
        }
        $decoded = json_decode(File::get($path), true);

        return is_array($decoded) ? $decoded : null;
    }

    private function localeDir(string $app, string $locale): string
    {
        return resource_path('localizations/'.$app.'/'.$locale);
    }

    private function etagFor(string $app, string $locale, string $raw, string $suffix = ''): string
    {
        return '"loc-'.$app.'-'.$locale.$suffix.'-'.hash('xxh128', $raw).'"';
    }

    private function sanitizeNamespace(string $namespace): string
    {
        if (! preg_match('/^[a-z0-9_-]+$/i', $namespace)) {
            throw new NotFoundHttpException('Invalid namespace.');
        }

        return $namespace;
    }

    private function assertApp(string $app): void
    {
        if (! $this->isValidApp($app)) {
            throw new NotFoundHttpException('App not supported.');
        }
    }

    private function assertLocale(string $locale): void
    {
        if (! $this->isValidLocale($locale)) {
            throw new NotFoundHttpException('Locale not supported.');
        }
    }
}
