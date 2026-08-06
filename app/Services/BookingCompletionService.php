<?php

namespace App\Services;

use App\Constants\AppConst;
use App\Events\BookingCompleteEvent;
use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\HotelReservation;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Boundary B — capture / complete a paid booking atomically.
 *
 * Locks booking + payment, marks money state paid/COMPLETE, confirms hotel
 * reservations, writes the financial ledger once, then dispatches
 * BookingCompleteEvent after commit (QR/SMS/cabin map/attribution).
 */
class BookingCompletionService
{
    public function __construct(
        private readonly FinancialLedgerService $ledger,
    ) {
    }

    /**
     * @param  array{status?: string, paid_amount?: float, store_amount?: float, dues?: float, payment_method?: string|null}  $paymentOverrides
     */
    public function complete(Booking $booking, ?Payment $payment = null, array $paymentOverrides = []): Booking
    {
        $bookingId = (int) $booking->id;

        $completed = DB::transaction(function () use ($bookingId, $payment, $paymentOverrides) {
            /** @var Booking $locked */
            $locked = Booking::query()->whereKey($bookingId)->lockForUpdate()->firstOrFail();

            if (in_array($locked->status, [AppConst::BOOKING_COMPLETE, AppConst::BOOKING_ADVANCE], true)
                && $this->hasSuccessfulPayment($locked, $payment)) {
                return $locked->fresh(['bookingItems', 'customer', 'payment', 'payments']);
            }

            $pay = $this->resolvePayment($locked, $payment);
            if ($pay) {
                Payment::query()->whereKey($pay->id)->lockForUpdate()->first();
                $pay->refresh();
                $this->markPaymentSuccess($pay, $locked, $paymentOverrides);
            }

            $status = $paymentOverrides['status']
                ?? ((isset($paymentOverrides['dues']) && (float) $paymentOverrides['dues'] > 0)
                    ? AppConst::BOOKING_ADVANCE
                    : AppConst::BOOKING_COMPLETE);

            $locked->update([
                'status' => $status,
                'payment_status' => 1,
            ]);

            BookingItem::query()
                ->where('booking_id', $locked->id)
                ->where('status', '!=', AppConst::BOOKING_ITEM_CANCELLED)
                ->update(['status' => AppConst::BOOKING_ITEM_ACTIVE]);

            $this->confirmHotelReservations($locked);

            $locked = $locked->fresh(['bookingItems', 'customer', 'payment', 'payments']);
            $this->ledger->recordBookingPaid($locked);

            return $locked;
        }, 3);

        DB::afterCommit(function () use ($completed) {
            BookingCompleteEvent::dispatch($completed->fresh(['bookingItems', 'customer', 'payment']) ?? $completed);
        });

        return $completed->fresh(['bookingItems', 'customer', 'payment', 'payments']) ?? $completed;
    }

    /**
     * Dispatch completion side-effects only when booking is already paid/COMPLETE.
     */
    public function dispatchCompleteEvent(Booking $booking): void
    {
        if (! $this->isPaidComplete($booking)) {
            return;
        }

        $fresh = $booking->fresh(['bookingItems', 'customer', 'payment', 'payments']) ?? $booking;

        if (DB::transactionLevel() > 0) {
            DB::afterCommit(fn () => BookingCompleteEvent::dispatch($fresh));

            return;
        }

        BookingCompleteEvent::dispatch($fresh);
    }

    public function isPaidComplete(Booking $booking): bool
    {
        if (! in_array($booking->status, [AppConst::BOOKING_COMPLETE, AppConst::BOOKING_ADVANCE], true)) {
            return false;
        }

        return $this->hasSuccessfulPayment($booking, null);
    }

    private function hasSuccessfulPayment(Booking $booking, ?Payment $payment): bool
    {
        $pay = $this->resolvePayment($booking, $payment);
        if (! $pay) {
            return (int) ($booking->payment_status ?? 0) === 1;
        }

        $status = strtolower((string) $pay->status);

        return in_array($status, ['success', 'paid', 'advance', AppConst::PAYMENT_SUCCESS], true)
            || (method_exists($pay, 'isCollected') && $pay->isCollected());
    }

    private function resolvePayment(Booking $booking, ?Payment $payment): ?Payment
    {
        if ($payment) {
            return $payment;
        }

        $booking->loadMissing(['payment', 'payments']);

        return $booking->payment
            ?? $booking->payments?->sortByDesc('id')->first()
            ?? Payment::query()->where('booking_id', $booking->id)->orderByDesc('id')->first();
    }

    private function markPaymentSuccess(Payment $payment, Booking $booking, array $overrides): void
    {
        $paid = (float) ($overrides['paid_amount']
            ?? $payment->paid_amount
            ?: $booking->total_payable);
        $store = (float) ($overrides['store_amount']
            ?? $payment->store_amount
            ?: $paid);
        $dues = (float) ($overrides['dues'] ?? 0);

        $attrs = [
            'status' => $dues > 0 ? 'advance' : 'success',
            'dues' => $dues,
            'paid_amount' => $paid,
            'store_amount' => $store,
        ];
        if (array_key_exists('payment_method', $overrides) && $overrides['payment_method'] !== null) {
            $attrs['payment_method'] = $overrides['payment_method'];
        }

        $payment->update($attrs);
    }

    private function confirmHotelReservations(Booking $booking): void
    {
        if (! Schema::hasTable('hotel_reservations') || ! class_exists(HotelReservation::class)) {
            return;
        }

        HotelReservation::query()
            ->where('booking_id', $booking->id)
            ->whereIn('status', [
                HotelReservation::STATUS_PENDING_PAYMENT,
                'pending',
                'PENDING',
            ])
            ->update(['status' => HotelReservation::STATUS_CONFIRMED]);
    }
}
