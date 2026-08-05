<?php

namespace App\Listeners;

use App\Events\BookingCompleteEvent;
use App\Services\FinancialLedgerService;

class RecordFinancialEventsOnBookingPaid
{
    public function __construct(private readonly FinancialLedgerService $ledger)
    {
    }

    public function handle(BookingCompleteEvent $event): void
    {
        $booking = $event->booking;
        if ($booking) {
            $this->ledger->recordBookingPaid($booking->fresh(['payments', 'bookingItems']) ?? $booking);
        }
    }
}
