<?php

namespace App\Console\Commands;

use App\Services\Hotel\HotelBookingService;
use Illuminate\Console\Command;

class HotelMaintainCommand extends Command
{
    protected $signature = 'hotel:maintain';

    protected $description = 'Expire stale hotel holds and fail unpaid hotel reservations, releasing inventory';

    public function handle(HotelBookingService $hotelBooking): int
    {
        $holds = $hotelBooking->expireStaleHolds();
        $res = $hotelBooking->failUnpaidReservations();
        if ($holds > 0 || $res > 0) {
            $this->info("Hotel maintenance: expired holds={$holds}, failed unpaid reservations={$res}");
        }

        return self::SUCCESS;
    }
}
