<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Services\PendingBookingPaymentWindow;
use Illuminate\Console\Command;

class HandlePendingBookings extends Command
{
    protected $signature = 'booking:pending';

    protected $description = 'Fail PENDING bookings that were not paid within the configured window (default 10 minutes) and release items';

    public function handle(): int
    {
        $bookings = PendingBookingPaymentWindow::queryExpiredPendingBookings()->get();
        $count = 0;
        foreach ($bookings as $booking) {
            try {
                PendingBookingPaymentWindow::failBookingForNonPayment($booking);
                $count++;
            } catch (\Throwable $e) {
                $this->error($booking->id.': '.$e->getMessage());
            }
        }
        if ($count > 0) {
            $this->info("Expired {$count} unpaid pending booking(s).");
        }

        return self::SUCCESS;
    }
}
