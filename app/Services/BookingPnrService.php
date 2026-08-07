<?php

namespace App\Services;

use App\Models\Booking;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

/**
 * Generates and resolves public booking PNRs.
 *
 * Format: D{ymd}-{A-Z}{5digits}-{A-Z}{5digits}
 * Example: D260807-K48210-Q03945
 */
class BookingPnrService
{
    public const PATTERN = '/^D\d{6}-[A-Z]\d{5}-[A-Z]\d{5}$/';

    private const LETTERS = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';

    private const MAX_ATTEMPTS = 25;

    public function isValid(?string $pnr): bool
    {
        return is_string($pnr) && preg_match(self::PATTERN, strtoupper(trim($pnr))) === 1;
    }

    public function normalize(?string $pnr): ?string
    {
        if (! is_string($pnr)) {
            return null;
        }

        $normalized = strtoupper(trim($pnr));

        return $this->isValid($normalized) ? $normalized : null;
    }

    /**
     * Generate a unique PNR for a booking date.
     */
    public function generate(Carbon|string|null $date = null): string
    {
        $ymd = $this->dateSegment($date);

        for ($attempt = 0; $attempt < self::MAX_ATTEMPTS; $attempt++) {
            $candidate = sprintf(
                'D%s-%s%05d-%s%05d',
                $ymd,
                $this->randomLetter(),
                random_int(0, 99999),
                $this->randomLetter(),
                random_int(0, 99999)
            );

            if (! $this->exists($candidate)) {
                return $candidate;
            }
        }

        throw new \RuntimeException('Unable to generate a unique booking PNR after '.self::MAX_ATTEMPTS.' attempts.');
    }

    public function ensureFor(Booking $booking, bool $persist = true): string
    {
        if ($this->isValid($booking->pnr ?? null)) {
            return (string) $booking->pnr;
        }

        $pnr = $this->generate($booking->booking_date ?: $booking->created_at);
        $booking->pnr = $pnr;

        if ($persist && $booking->exists) {
            $booking->saveQuietly();
        }

        return $pnr;
    }

    /**
     * Resolve a booking by public PNR, or by numeric ID for trusted/admin callers.
     */
    public function findBooking(string $identifier, bool $allowNumericId = false): ?Booking
    {
        $identifier = trim($identifier);
        if ($identifier === '') {
            return null;
        }

        $pnr = $this->normalize($identifier);
        if ($pnr !== null) {
            return Booking::query()->where('pnr', $pnr)->first();
        }

        if ($allowNumericId && ctype_digit($identifier)) {
            return Booking::query()->find((int) $identifier);
        }

        return null;
    }

    public function exists(string $pnr): bool
    {
        if (! Schema::hasTable('bookings') || ! Schema::hasColumn('bookings', 'pnr')) {
            return false;
        }

        return Booking::query()->where('pnr', $pnr)->exists();
    }

    private function dateSegment(Carbon|string|null $date): string
    {
        try {
            if ($date instanceof Carbon) {
                return $date->format('ymd');
            }
            if (is_string($date) && trim($date) !== '') {
                return Carbon::parse($date)->format('ymd');
            }
        } catch (\Throwable) {
            // fall through
        }

        return now()->format('ymd');
    }

    private function randomLetter(): string
    {
        return self::LETTERS[random_int(0, strlen(self::LETTERS) - 1)];
    }
}
