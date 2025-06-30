<?php

namespace Modules\Payment\Listeners;

use App\Constants\AppConst;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\DB;
use App\Jobs\CabinMappingBookingJob;
use App\Jobs\SendBookingInvoiceJob;
use App\Services\BookingService;
use App\Services\CalculationService;
use Modules\Booking\Jobs\BookingCommissionCalculationJob;
use Modules\Payment\Events\PaymentCompleteEvent;

class PaymentSuccessListener
{
    /**
     * @var BookingService
     */
    private $bookingService;
    private $calculation;

    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct(BookingService $bookingService, CalculationService $calculation)
    {
        $this->bookingService = $bookingService;
        $this->calculation = $calculation;
    }

    /**
     * Handle the event.
     *
     * @param PaymentCompleteEvent $event
     * @return void
     */
    public function handle(PaymentCompleteEvent $event)
    {
        if ($event->booking->status !== AppConst::BOOKING_COMPLETE) {
            dispatch(new CabinMappingBookingJob($event->booking, AppConst::BOOKING_ITEM_ACTIVE));
            if($event->booking->status === AppConst::BOOKING_COMPLETE && $event->booking->officer->hasAnyRole([AppConst::AGENT_ROLE, 'supervisor'])) {
                dispatch(new BookingCommissionCalculationJob($event->booking, $this->calculation));
            }
            if ($this->bookingService->iAmNotBlacker($event->booking->bookingItems, $event->booking->customer_id)) {
                dispatch(new SendBookingInvoiceJob($event->booking));
            }
        }
    }
}
