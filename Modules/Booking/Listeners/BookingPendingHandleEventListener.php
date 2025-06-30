<?php

namespace Modules\Booking\Listeners;

use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use App\Services\BookingService;
use Modules\Booking\Events\BookingPendingHandleEvent;

class BookingPendingHandleEventListener
{
    /**
     * @var BookingService
     */
    private $booking;

    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct(BookingService $bookingService)
    {
        $this->booking = $bookingService;
    }

    /**
     * Handle the event.
     *
     * @param BookingPendingHandleEvent $event
     * @return void
     */
    public function handle(BookingPendingHandleEvent $event)
    {
        $this->booking->checkPaymentTransaction($event->booking);
    }
}
