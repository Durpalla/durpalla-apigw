<?php

namespace App\Console\Commands;

use App\Services\Hotel\HotelBookingService;
use App\Services\Hotel\HotelReviewEligibilityService;
use Illuminate\Console\Command;

class HotelMaintainCommand extends Command
{
    protected $signature = 'hotel:maintain';

    protected $description = 'Expire stale hotel holds and fail unpaid hotel reservations, releasing inventory';

    public function handle(HotelBookingService $hotelBooking): int
    {
        $holds = $hotelBooking->expireStaleHolds();
        $res = $hotelBooking->failUnpaidReservations();
        $prompts = app(HotelReviewEligibilityService::class)->dispatchCheckoutReviewPrompts();
        if ($holds > 0 || $res > 0 || $prompts > 0) {
            $this->info("Hotel maintenance: expired holds={$holds}, failed unpaid reservations={$res}, review prompts={$prompts}");
        }

        return self::SUCCESS;
    }
}
