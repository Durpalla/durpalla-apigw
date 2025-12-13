<?php

namespace App\Observers;

use App\Constants\AppConst;
use Illuminate\Support\Facades\Cache;
use App\Models\Booking;
use App\Helpers\LogActivity;
use App\Services\CalculationService;
use Modules\Booking\Jobs\BookingSessionReleaseJob;

class BookingObserver
{
    public function created(Booking $booking)
    {
        if (in_array($booking->status, [AppConst::BOOKING_COMPLETE, AppConst::BOOKING_ADVANCE]))
            Cache::forget('daily_booking_' . $booking->booking_date);
    }

    /**
     * Handle the booking "updated" event.
     *
     * @param Booking $booking
     * @return void
     */
    public function updated(Booking $booking)
    {
        if (in_array($booking->status, [AppConst::BOOKING_COMPLETE, AppConst::BOOKING_ADVANCE]))
            Cache::forget('daily_booking_' . $booking->booking_date);
    }

    /**
     * Handle the booking "deleted" event.
     *
     * @param Booking $booking
     * @return void
     */
    public function deleted(Booking $booking)
    {
        //
    }

    /**
     * Handle the booking "restored" event.
     *
     * @param Booking $booking
     * @return void
     */
    public function restored(Booking $booking)
    {
        //
    }

    /**
     * Handle the booking "force deleted" event.
     *
     * @param Booking $booking
     * @return void
     */
    public function forceDeleted(Booking $booking)
    {
        //
    }
}
