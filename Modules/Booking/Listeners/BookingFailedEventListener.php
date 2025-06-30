<?php

namespace Modules\Booking\Listeners;

use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Modules\Booking\Events\BookingFailedEvent;
use Modules\Booking\Jobs\BookingFailedJob;
use Modules\Booking\Notifications\BookingFailedNotification;

class BookingFailedEventListener
{
    /**
     * Handle the event.
     *
     * @param BookingFailedEvent $event
     * @return void
     */
    public function handle(BookingFailedEvent $event)
    {
        BookingFailedJob::dispatch($event->booking);
        $event->booking->customer->notify(new BookingFailedNotification($event->booking));
    }
}
