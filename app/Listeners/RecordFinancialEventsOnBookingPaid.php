<?php

namespace App\Listeners;

use App\Constants\AppConst;
use App\Events\BookingCompleteEvent;
use App\Models\Payment;
use App\Services\FinancialLedgerService;

/**
 * Emits financial ledger events when a booking completes as paid.
 * Rejects PENDING bookings so charge/VAT ledger is never stamped too early.
 */
class RecordFinancialEventsOnBookingPaid
{
    public function __construct(private readonly FinancialLedgerService $ledger)
    {
    }

    public function handle(BookingCompleteEvent $event): void
    {
        $booking = $event->booking->fresh(['payments', 'bookingItems']) ?? $event->booking;
        if (! $this->isEligible($booking)) {
            return;
        }

        $this->ledger->recordBookingPaid($booking);
    }

    private function isEligible($booking): bool
    {
        if (! in_array($booking->status, [AppConst::BOOKING_COMPLETE, AppConst::BOOKING_ADVANCE], true)) {
            return false;
        }

        $payment = $booking->payments?->sortByDesc('id')->first()
            ?? Payment::query()->where('booking_id', $booking->id)->orderByDesc('id')->first();

        if (! $payment) {
            return (int) ($booking->payment_status ?? 0) === 1;
        }

        $status = strtolower((string) $payment->status);

        return in_array($status, ['success', 'paid', 'advance', 'verified', AppConst::PAYMENT_SUCCESS], true);
    }
}
