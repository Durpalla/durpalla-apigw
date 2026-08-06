<?php

namespace App\Listeners;

use App\Constants\AppConst;
use App\Events\BookingCompleteEvent;
use App\Jobs\BookingCreatedSmsJob;
use App\Jobs\BookingFcmNotificationJob;
use App\Jobs\CabinMappingBookingJob;
use App\Services\AgentReferralAttributionService;

/**
 * apigw side-effects for paid booking completion (cabin map, SMS, FCM, attribution).
 */
class BookingCompleteSideEffectsListener
{
    public function handle(BookingCompleteEvent $event): void
    {
        $booking = $event->booking;

        dispatch(new CabinMappingBookingJob($booking, AppConst::BOOKING_ITEM_ACTIVE));

        if (in_array($booking->status, [AppConst::BOOKING_COMPLETE, AppConst::BOOKING_ADVANCE], true)) {
            dispatch(new BookingCreatedSmsJob($booking));
            dispatch(new BookingFcmNotificationJob($booking));
        }

        if ($booking->status === AppConst::BOOKING_COMPLETE) {
            app(AgentReferralAttributionService::class)->attribute($booking);
        }
    }
}
