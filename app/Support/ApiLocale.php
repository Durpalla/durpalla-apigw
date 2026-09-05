<?php

namespace App\Support;

use Illuminate\Http\Request;

final class ApiLocale
{
    public const DEFAULT = 'en';

    private function __construct()
    {
    }

    /**
     * @return list<string>
     */
    public static function supported(): array
    {
        return array_keys(config('localization.locales', []));
    }

    public static function isSupported(string $locale): bool
    {
        return in_array($locale, self::supported(), true);
    }

    public static function normalize(?string $lang): string
    {
        $lang = strtolower(trim(str_replace('_', '-', (string) $lang)));
        if ($lang === '') {
            return self::DEFAULT;
        }

        $primary = explode('-', $lang)[0];

        if (self::isSupported($primary)) {
            return $primary;
        }

        if (self::isSupported($lang)) {
            return $lang;
        }

        return self::DEFAULT;
    }

    /**
     * Accept-Language → Content-Language → en.
     */
    public static function resolveFromRequest(?Request $request = null): string
    {
        $request ??= request();

        if ($request === null) {
            return self::DEFAULT;
        }

        $accept = trim((string) $request->header('Accept-Language', ''));
        if ($accept !== '') {
            $fromAccept = self::parseAcceptLanguage($accept);
            if ($fromAccept !== null) {
                return $fromAccept;
            }
        }

        $content = trim((string) $request->header('Content-Language', ''));
        if ($content !== '') {
            $normalized = self::normalize($content);
            if ($normalized !== self::DEFAULT || $content === 'en') {
                return $normalized;
            }
        }

        return self::DEFAULT;
    }

    /**
     * Parse Accept-Language and return the first supported tag.
     */
    public static function parseAcceptLanguage(string $header): ?string
    {
        $parts = array_map('trim', explode(',', $header));

        foreach ($parts as $part) {
            if ($part === '') {
                continue;
            }

            $tag = trim(explode(';', $part)[0]);
            $normalized = self::normalize($tag);

            if (self::isSupported($normalized)) {
                return $normalized;
            }
        }

        return null;
    }

    public static function apply(?Request $request = null): string
    {
        $locale = self::resolveFromRequest($request);
        app()->setLocale($locale);

        return $locale;
    }
}
