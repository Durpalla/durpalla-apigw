<?php

namespace Modules\Cancellation\Listeners;

use Modules\Cancellation\Events\CancellationRequestEvent;
use Modules\Cancellation\Jobs\CancellationRequestEmailToSupportJob;

class CancellationRequestEventListener
{
    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     *
     * @param CancellationRequestEvent $event
     * @return void
     */
    public function handle(CancellationRequestEvent $event)
    {
        dispatch(new CancellationRequestEmailToSupportJob($event->cancellation));
    }
}
