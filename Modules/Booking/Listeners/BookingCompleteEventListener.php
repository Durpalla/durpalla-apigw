<?php

namespace Modules\Booking\Listeners;

use App\Constants\AppConst;
use App\Jobs\CabinMappingBookingJob;
use App\Jobs\PartnerCommissionJob;
use App\Models\Booking;
use App\Models\BookingItem;
use App\Services\CalculationService;
use Modules\Booking\Events\BookingCompleteEvent;
use Modules\Booking\Jobs\AdvanceBookingFlagJob;
use Modules\Booking\Jobs\BookingChargeAdjustmentJob;
use Modules\Booking\Jobs\BookingCommissionCalculationJob;
use Modules\Booking\Jobs\BookingCreatedSmsJob;
use Modules\Booking\Jobs\BookingFcmNotificationJob;
use Modules\Booking\Jobs\BookingQrcodeGenerateJob;

class BookingCompleteEventListener
{
    /**
     * @var CalculationService
     */
    public $calculation;

    public function __construct(CalculationService $calculationService)
    {
        $this->calculation = $calculationService;
    }

    /**
     * Handle the event.
     *
     * @param BookingCompleteEvent $event
     * @return void
     */
    public function handle(BookingCompleteEvent $event)
    {
        dispatch(new BookingQrcodeGenerateJob($event->booking));
        dispatch(new CabinMappingBookingJob($event->booking, AppConst::BOOKING_ITEM_ACTIVE));
        if(in_array($event->booking->status, [AppConst::BOOKING_COMPLETE, AppConst::BOOKING_ADVANCE])) {
            if ($this->iAmNotBlacker($event->booking)) {
                dispatch(new BookingCreatedSmsJob($event->booking));
            }

            if($event->booking->booking_party === AppConst::PARTY_JOLZAN) {
                dispatch(new PartnerCommissionJob($event->booking, $this->calculation));
            }
            dispatch(new BookingFcmNotificationJob($event->booking));
        }
        if($event->booking->status === AppConst::BOOKING_ADVANCE) {
            dispatch(new AdvanceBookingFlagJob($event->booking));
        }
        if($event->booking->status === AppConst::BOOKING_COMPLETE) {
            if($event->booking->officer->hasAnyRole([AppConst::AGENT_ROLE, 'supervisor'])) {
                dispatch(new BookingCommissionCalculationJob($event->booking, $this->calculation));
            }
        }
    }

    private function iAmNotBlacker(Booking $booking): bool
    {
        $notBlacker = true;
        collect($booking->bookingItems)->groupBy('trip_id')
            ->each(function ($item, $tripID) use (&$notBlacker, $booking) {
                $item->groupBy('booking_type')
                    ->each(function ($item, $type) use (&$notBlacker, $tripID, $booking) {
                        if (BookingItem::where(['trip_id' => $tripID, 'booking_type' => $type, 'customer_id' => $booking->customer->id])->count() > getOption('max_' . $type . '_booking', 2)) {
                            $notBlacker = false;
                        }
                    });
            });
        return true;
    }
}
