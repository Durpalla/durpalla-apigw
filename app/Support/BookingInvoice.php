<?php

namespace App\Support;

use App\Models\Booking;
use App\Services\BookingPnrService;
use Illuminate\Support\Facades\URL;

/**
 * Channel-agnostic booking invoice helpers shared by customer, agent,
 * merchant, and web clients. All apps download the same signed
 * PDF invoice at route {@see invoice.download}.
 */
final class BookingInvoice
{
    public const LANG_EN = 'en';

    public const LANG_BN = 'bn';

    private function __construct()
    {
    }

    /**
     * Public booking reference (PNR), e.g. "D260807-K48210-Q03945".
     */
    public static function formatReference(Booking $booking): string
    {
        return app(BookingPnrService::class)->ensureFor($booking);
    }

    /**
     * Normalize to supported invoice locales: en | bn.
     */
    public static function normalizeLang(?string $lang): string
    {
        $lang = strtolower(trim((string) $lang));
        if ($lang === '') {
            return self::LANG_EN;
        }
        if (str_starts_with($lang, 'bn')) {
            return self::LANG_BN;
        }
        if (str_starts_with($lang, 'en')) {
            return self::LANG_EN;
        }

        return self::LANG_EN;
    }

    /**
     * Resolve lang from explicit value, ?lang=, Accept-Language, or app locale.
     */
    public static function resolveLang(?string $explicit = null): string
    {
        if (is_string($explicit) && trim($explicit) !== '') {
            return self::normalizeLang($explicit);
        }

        try {
            $request = request();
        } catch (\Throwable) {
            $request = null;
        }

        if ($request !== null) {
            $queryLang = $request->query('lang');
            if (is_string($queryLang) && trim($queryLang) !== '') {
                return self::normalizeLang($queryLang);
            }

            $header = (string) $request->header('Accept-Language', '');
            if ($header !== '') {
                $first = trim(explode(',', $header)[0]);
                $first = trim(explode(';', $first)[0]);

                return self::normalizeLang($first);
            }
        }

        return self::normalizeLang((string) config('app.locale', self::LANG_EN));
    }

    /**
     * Apply invoice locale for the current request lifecycle.
     */
    public static function applyLocale(?string $lang = null): string
    {
        $normalized = self::resolveLang($lang);
        app()->setLocale($normalized);

        return $normalized;
    }

    /**
     * Temporary signed URL for the common invoice PDF download.
     */
    public static function signedUrl(int|Booking $booking, int $expiresMinutes = 60, ?string $lang = null): string
    {
        $id = $booking instanceof Booking ? (int) $booking->id : $booking;

        return URL::temporarySignedRoute(
            'invoice.download',
            now()->addMinutes(max(1, $expiresMinutes)),
            [
                'id' => $id,
                'lang' => self::resolveLang($lang),
            ]
        );
    }

    /**
     * Temporary signed URL for the HTML invoice preview (mobile WebView).
     */
    public static function signedHtmlUrl(int|Booking $booking, int $expiresMinutes = 60, ?string $lang = null): string
    {
        $id = $booking instanceof Booking ? (int) $booking->id : $booking;

        return URL::temporarySignedRoute(
            'invoice.view',
            now()->addMinutes(max(1, $expiresMinutes)),
            [
                'id' => $id,
                'lang' => self::resolveLang($lang),
            ]
        );
    }
}
