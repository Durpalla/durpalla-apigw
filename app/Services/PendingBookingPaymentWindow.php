<?php

namespace App\Services;

use App\Constants\AppConst;
use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\HotelReservation;
use App\Models\Payment;
use App\Models\ScheduleCabinMapping;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Unpaid customer bookings (PENDING) must be paid within a short window (default 10 minutes).
 * After that, {@see HandlePendingBookings} marks them failed and releases cabin mappings.
 */
final class PendingBookingPaymentWindow
{
    /**
     * Minutes from booking creation until payment is no longer accepted.
     * Configurable via options table key `payment_lock_period` (minutes), default 10.
     */
    public static function deadlineMinutes(): int
    {
        $m = (int) getOption('payment_lock_period', 10);

        return max(1, min(240, $m));
    }

    public static function paymentDeadline(Booking $booking): Carbon
    {
        return Carbon::parse($booking->created_at)->addMinutes(self::deadlineMinutes());
    }

    public static function hasNonPayableItems(Booking $booking): bool
    {
        if (! $booking->relationLoaded('bookingItems')) {
            $booking->load('bookingItems');
        }

        return $booking->bookingItems->contains(function (BookingItem $item): bool {
            return in_array((int) $item->status, [
                AppConst::BOOKING_ITEM_CANCELLED,
                AppConst::BOOKING_ITEM_FAILED,
            ], true);
        });
    }

    public static function isWithinPaymentWindow(Booking $booking): bool
    {
        if ($booking->status !== AppConst::BOOKING_PENDING) {
            return false;
        }

        $hotelRes = HotelReservation::query()
            ->where('booking_id', $booking->id)
            ->where('status', HotelReservation::STATUS_PENDING_PAYMENT)
            ->first();
        if ($hotelRes?->payment_due_at) {
            return now()->lte($hotelRes->payment_due_at);
        }

        if (self::hasNonPayableItems($booking)) {
            return false;
        }

        return now()->lte(self::paymentDeadline($booking));
    }

    /**
     * Human-readable block reason for APIs, or null if payment may proceed.
     */
    public static function reasonPaymentBlocked(Booking $booking): ?string
    {
        if (in_array($booking->status, [
            AppConst::BOOKING_FAILED,
            AppConst::BOOKING_CANCELLED,
            AppConst::BOOKING_REJECTED,
        ], true)) {
            return __('This booking was cancelled or declined and can no longer be paid.');
        }
        if ($booking->status !== AppConst::BOOKING_PENDING) {
            return __('This booking is not awaiting payment.');
        }

        $hotelRes = HotelReservation::query()
            ->where('booking_id', $booking->id)
            ->where('status', HotelReservation::STATUS_PENDING_PAYMENT)
            ->first();
        if ($hotelRes?->payment_due_at) {
            if (now()->gt($hotelRes->payment_due_at)) {
                return __('Payment time has expired. Please book again (payment window: :minutes minutes).', [
                    'minutes' => max(1, (int) config('hotel.payment_window_minutes', 10)),
                ]);
            }

            return null;
        }

        if (self::hasNonPayableItems($booking)) {
            return __('This booking has cancelled or released items and can no longer be paid.');
        }
        if (! self::isWithinPaymentWindow($booking)) {
            return __('Payment time has expired. Please book again (payment window: :minutes minutes).', [
                'minutes' => self::deadlineMinutes(),
            ]);
        }

        return null;
    }

    /**
     * PENDING bookings whose payment window has passed (created_at + deadline <= now).
     *
     * @return Builder<Booking>
     */
    public static function queryExpiredPendingBookings(): Builder
    {
        $cutoff = now()->subMinutes(self::deadlineMinutes());

        return Booking::query()
            ->with(['bookingItems'])
            ->where('status', AppConst::BOOKING_PENDING)
            ->where('created_at', '<=', $cutoff)
            ->whereDoesntHave('hotelReservation');
    }

    /**
     * Same side-effects as Modules\Booking\Jobs\BookingFailedJob (release seats, mark failed).
     */
    public static function failBookingForNonPayment(Booking $booking): void
    {
        DB::transaction(function () use ($booking) {
            $booking->refresh();
            if ($booking->status !== AppConst::BOOKING_PENDING) {
                return;
            }

            $booking->update(['status' => AppConst::BOOKING_FAILED]);

            Payment::query()->where('booking_id', $booking->id)->update(['status' => 'failed']);

            $booking->loadMissing('bookingItems');
            $booking->bookingItems->each(function (BookingItem $item): void {
                $item->update(['status' => AppConst::BOOKING_ITEM_CANCELLED]);
                if ($item->booking_type === 'deck') {
                    return;
                }
                $mapping = ScheduleCabinMapping::query()
                    ->where('schedule_id', $item->trip_id)
                    ->where('cabin_id', $item->cabin_id)
                    ->first();
                if ($mapping) {
                    $mapping->update([
                        'booked' => AppConst::BOOKING_ITEM_PENDING,
                        'booking_id' => null,
                    ]);
                }
            });
        });
    }
}
