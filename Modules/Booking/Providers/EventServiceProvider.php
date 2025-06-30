<?php

namespace Modules\Booking\Providers;

use Closure;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Modules\Booking\Events\BookingCancelledEvent;
use Modules\Booking\Events\BookingCompleteEvent;
use Modules\Booking\Events\BookingFailedEvent;
use Modules\Booking\Events\BookingPendingHandleEvent;
use Modules\Booking\Listeners\BookingCancelledEventListener;
use Modules\Booking\Listeners\BookingCompleteEventListener;
use Modules\Booking\Listeners\BookingFailedEventListener;
use Modules\Booking\Listeners\BookingPendingHandleEventListener;

class EventServiceProvider extends ServiceProvider
{
    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides(): array
    {
        return [];
    }
}
