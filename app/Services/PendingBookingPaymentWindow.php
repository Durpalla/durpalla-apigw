<?php

namespace App\Services;

use App\Constants\AppConst;
use App\Helpers\CommonHelper;
use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\HotelReservation;
use App\Models\Payment;
use App\Models\ScheduleCabinMapping;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Unpaid customer bookings (PENDING) must be paid within a short window (default 5 minutes).
 * On expiry: verify gateway → payment success or fail first, then booking follows.
 */
final class PendingBookingPaymentWindow
{
    /**
     * Minutes from booking creation until payment is no longer accepted.
     * Configurable via options table key `payment_lock_period` (minutes), default 5.
     */
    public static function deadlineMinutes(): int
    {
        $m = (int) getOption('payment_lock_period', 5);

        return max(1, min(240, $m));
    }

    public static function paymentDeadline(Booking $booking): Carbon
    {
        return Carbon::parse($booking->created_at)->addMinutes(self::deadlineMinutes());
    }

    /**
     * Effective payment due timestamp (hotel payment_due_at when set, else created_at + window).
     */
    public static function resolvePaymentDueAt(Booking $booking): Carbon
    {
        $hotelRes = HotelReservation::query()
            ->where('booking_id', $booking->id)
            ->where('status', HotelReservation::STATUS_PENDING_PAYMENT)
            ->first();
        if ($hotelRes?->payment_due_at) {
            return Carbon::parse($hotelRes->payment_due_at);
        }

        return self::paymentDeadline($booking);
    }

    /**
     * Payload for agent APIs: payment countdown / retry eligibility.
     *
     * @return array{payment_due_at:?string,payment_due_at_ms:?int,can_pay:bool,gateway_id:?int}
     */
    public static function paymentWindowPayload(Booking $booking): array
    {
        if ($booking->status !== AppConst::BOOKING_PENDING) {
            return [
                'payment_due_at' => null,
                'payment_due_at_ms' => null,
                'can_pay' => false,
                'gateway_id' => null,
            ];
        }

        $due = self::resolvePaymentDueAt($booking);
        $payment = $booking->relationLoaded('payment')
            ? $booking->payment
            : $booking->payment()->first();
        $gatewayId = $payment?->gateway_id ? (int) $payment->gateway_id : null;
        $canPay = self::reasonPaymentBlocked($booking) === null;

        return [
            'payment_due_at' => $due->toIso8601String(),
            'payment_due_at_ms' => (int) ($due->getTimestamp() * 1000),
            'can_pay' => $canPay,
            'gateway_id' => $gatewayId,
        ];
    }

    /**
     * Agent voids their own unpaid PENDING booking (releases seats / marks cancelled).
     */
    public static function cancelUnpaidPendingBooking(Booking $booking): void
    {
        DB::transaction(function () use ($booking) {
            $booking->refresh();
            if ($booking->status !== AppConst::BOOKING_PENDING) {
                throw new \InvalidArgumentException(__('This booking is not awaiting payment.'));
            }

            $booking->update(['status' => AppConst::BOOKING_CANCELLED]);

            Payment::query()
                ->where('booking_id', $booking->id)
                ->whereNotIn('status', ['success', 'paid', 'complete', 'completed', 'advance'])
                ->update(['status' => 'cancel']);

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

            HotelReservation::query()
                ->where('booking_id', $booking->id)
                ->where('status', HotelReservation::STATUS_PENDING_PAYMENT)
                ->update(['status' => HotelReservation::STATUS_CANCELLED]);
        });
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
            if (! app()->environment('local') && now()->gt($hotelRes->payment_due_at)) {
                return __('Payment time has expired. Please book again (payment window: :minutes minutes).', [
                    'minutes' => max(1, (int) config('hotel.payment_window_minutes', 5)),
                ]);
            }

            return null;
        }

        if (self::hasNonPayableItems($booking)) {
            return __('This booking has cancelled or released items and can no longer be paid.');
        }
        if (! app()->environment('local') && ! self::isWithinPaymentWindow($booking)) {
            return __('Payment time has expired. Please book again (payment window: :minutes minutes).', [
                'minutes' => self::deadlineMinutes(),
            ]);
        }

        return null;
    }

    /**
     * PENDING bookings whose payment window has passed
     * (transport: created_at + deadline; hotel: reservation payment_due_at).
     *
     * @return Builder<Booking>
     */
    public static function queryExpiredPendingBookings(): Builder
    {
        $cutoff = now()->subMinutes(self::deadlineMinutes());

        return Booking::query()
            ->with(['bookingItems', 'payment', 'hotelReservation'])
            ->where('status', AppConst::BOOKING_PENDING)
            ->where(function (Builder $q) use ($cutoff): void {
                $q->where(function (Builder $transport) use ($cutoff): void {
                    $transport->whereDoesntHave('hotelReservation')
                        ->where('created_at', '<=', $cutoff);
                })->orWhereHas('hotelReservation', function (Builder $hotel): void {
                    $hotel->where('status', HotelReservation::STATUS_PENDING_PAYMENT)
                        ->whereNotNull('payment_due_at')
                        ->where('payment_due_at', '<=', now());
                });
            });
    }

    /**
     * If this PENDING booking is past its payment window, fail it now (for API reads / verify).
     *
     * @return 'completed'|'failed'|'skipped'
     */
    public static function resolveIfPaymentWindowExpired(Booking $booking): string
    {
        $booking->refresh();
        if ($booking->status !== AppConst::BOOKING_PENDING) {
            return 'skipped';
        }

        $dueAt = self::resolvePaymentDueAt($booking);
        if (now()->lte($dueAt)) {
            return 'skipped';
        }

        return self::resolveExpiredPendingBooking($booking);
    }

    public static function paymentLooksSuccessful(?Payment $payment): bool
    {
        if (! $payment) {
            return false;
        }

        // Require settlement evidence so premature status=success (agent live
        // gateway create before pay) does not auto-complete on window expiry.
        return $payment->isCollected();
    }

    /**
     * Ask the gateway once whether the pending payment settled.
     * Leaves status unchanged on errors so the fail path can mark it.
     */
    public static function attemptGatewayVerify(?Payment $payment): void
    {
        if ($payment === null || self::paymentLooksSuccessful($payment)) {
            return;
        }

        $status = strtolower(trim((string) ($payment->status ?? '')));
        if (in_array($status, ['fail', 'failed', 'cancel', 'cancelled', 'void', 'success', 'paid', 'complete', 'completed'], true)) {
            return;
        }

        try {
            $payment->loadMissing(['gateway', 'booking']);
            if ($payment->gateway === null) {
                return;
            }

            $gwt = CommonHelper::purseGateway($payment->gateway);
            $data = [];
            $gwt->verify($payment, request(), $data);
            $payment->refresh();
        } catch (\Throwable $e) {
            Log::warning('Payment window expiry verify failed', [
                'payment_id' => $payment->id,
                'booking_id' => $payment->booking_id,
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Expired PENDING booking: verify → payment success/fail first → then booking.
     *
     * @return 'completed'|'failed'|'skipped'
     */
    public static function resolveExpiredPendingBooking(Booking $booking): string
    {
        $booking->refresh();
        if ($booking->status !== AppConst::BOOKING_PENDING) {
            return 'skipped';
        }

        $payment = Payment::query()
            ->where('booking_id', $booking->id)
            ->latest('id')
            ->first();

        // 1) Resolve payment first (success or leave pending for fail step).
        self::attemptGatewayVerify($payment);
        if ($payment !== null) {
            $payment->refresh();
        }

        if (self::paymentLooksSuccessful($payment)) {
            DB::transaction(function () use ($booking, $payment): void {
                $booking->refresh();
                if ($booking->status !== AppConst::BOOKING_PENDING || ! $payment) {
                    return;
                }

                $payment->refresh();
                $payment->setRelation('booking', $booking);
                $payment->successful();
            });

            return 'completed';
        }

        // 2) Payment → fail/void, then booking failed (Payment::failed order).
        self::failBookingForNonPayment($booking);

        return 'failed';
    }

    /**
     * Same side-effects as legacy BookingFailedJob (release seats, mark failed).
     */
    public static function failBookingForNonPayment(Booking $booking): void
    {
        DB::transaction(function () use ($booking) {
            $booking->refresh();
            if ($booking->status !== AppConst::BOOKING_PENDING) {
                return;
            }

            $payment = Payment::query()
                ->where('booking_id', $booking->id)
                ->latest('id')
                ->first();

            // Hotel (and shared Payment::failed) releases inventory + marks fail/void.
            if ($payment !== null) {
                $payment->loadMissing(['booking', 'bookingItems']);
                $payment->setRelation('booking', $booking);
                $payment->failed();

                return;
            }

            $booking->update(['status' => AppConst::BOOKING_FAILED]);

            HotelReservation::query()
                ->where('booking_id', $booking->id)
                ->where('status', HotelReservation::STATUS_PENDING_PAYMENT)
                ->update(['status' => HotelReservation::STATUS_FAILED]);

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
