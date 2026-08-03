<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Services\PendingBookingPaymentWindow;
use Illuminate\Console\Command;

class HandlePendingBookings extends Command
{
    protected $signature = 'booking:pending';

    protected $description = 'Resolve expired PENDING bookings: complete paid orders, fail and release unpaid ones';

    public function handle(): int
    {
        $bookings = PendingBookingPaymentWindow::queryExpiredPendingBookings()->get();
        $completed = 0;
        $failed = 0;
        foreach ($bookings as $booking) {
            try {
                $result = PendingBookingPaymentWindow::resolveExpiredPendingBooking($booking);
                if ($result === 'completed') {
                    $completed++;
                } elseif ($result === 'failed') {
                    $failed++;
                }
            } catch (\Throwable $e) {
                $this->error($booking->id.': '.$e->getMessage());
            }
        }
        if ($completed > 0) {
            $this->info("Completed {$completed} pending booking(s) with successful payment.");
        }
        if ($failed > 0) {
            $this->info("Expired {$failed} unpaid pending booking(s).");
        }

        return self::SUCCESS;
    }
}
