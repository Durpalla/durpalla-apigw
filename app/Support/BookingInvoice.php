<?php

namespace App\Support;

use App\Models\Booking;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;

/**
 * Channel-agnostic booking invoice helpers shared by customer, agent,
 * merchant, and web clients. All apps download/share the same signed
 * HTML invoice at route {@see invoice.download}.
 */
final class BookingInvoice
{
    private function __construct()
    {
    }

    /**
     * Human-friendly booking reference, e.g. "DPB-20260804-0015".
     */
    public static function formatReference(Booking $booking): string
    {
        $datePart = '00000000';
        try {
            $datePart = Carbon::parse($booking->booking_date ?: $booking->created_at)->format('Ymd');
        } catch (\Exception) {
            // fall back to zero date part
        }

        return 'DPB-'.$datePart.'-'.str_pad((string) $booking->id, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Temporary signed URL for the common invoice HTML template.
     */
    public static function signedUrl(int|Booking $booking, int $expiresMinutes = 60): string
    {
        $id = $booking instanceof Booking ? (int) $booking->id : $booking;

        return URL::temporarySignedRoute(
            'invoice.download',
            now()->addMinutes(max(1, $expiresMinutes)),
            ['id' => $id]
        );
    }
}
