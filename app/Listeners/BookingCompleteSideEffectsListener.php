<?php

namespace App\Listeners;

use App\Constants\AppConst;
use App\Events\BookingCompleteEvent;
use App\Events\MerchantLiveBookingEvent;
use App\Jobs\AgentBookingPushJob;
use App\Jobs\BookingCreatedSmsJob;
use App\Jobs\BookingFcmNotificationJob;
use App\Jobs\BookingQrcodeGenerateJob;
use App\Jobs\CabinMappingBookingJob;
use App\Models\Booking;
use App\Models\BookingItem;
use App\Services\AgentReferralAttributionService;
use Illuminate\Support\Facades\ClassExists;

/**
 * apigw side-effects for paid booking completion (cabin map, SMS, FCM, attribution).
 */
class BookingCompleteSideEffectsListener
{
    public function handle(BookingCompleteEvent $event): void
    {
        $booking = $event->booking;

        if (class_exists(BookingQrcodeGenerateJob::class)) {
            dispatch(new BookingQrcodeGenerateJob($booking));
        }
        dispatch(new CabinMappingBookingJob($booking, AppConst::BOOKING_ITEM_ACTIVE));

        if (in_array($booking->status, [AppConst::BOOKING_COMPLETE, AppConst::BOOKING_ADVANCE], true)) {
            if ($this->iAmNotBlacker($booking)) {
                dispatch(new BookingCreatedSmsJob($booking));
            }
            if (class_exists(BookingFcmNotificationJob::class)) {
                dispatch(new BookingFcmNotificationJob($booking));
            }
            if (class_exists(AgentBookingPushJob::class)) {
                dispatch(new AgentBookingPushJob($booking));
            }
        }

        if ($booking->status === AppConst::BOOKING_COMPLETE) {
            app(AgentReferralAttributionService::class)->attribute($booking);
            if (class_exists(MerchantLiveBookingEvent::class)
                && method_exists(MerchantLiveBookingEvent::class, 'dispatchForCompletedBooking')) {
                MerchantLiveBookingEvent::dispatchForCompletedBooking($booking);
            }
        }
    }

    private function iAmNotBlacker(Booking $booking): bool
    {
        return true;
    }
}
