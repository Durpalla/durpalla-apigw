<?php

namespace Modules\Vehicle\Listeners;

use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use App\Jobs\VehicleActiveOnScheduleActiveJob;
use App\Services\TripService;
use Modules\Vehicle\Events\VehicleInactiveEvent;
use Modules\Vehicle\Jobs\SchedulePauseOnVehicleInactive;
use Modules\Vehicle\Jobs\VehicleActiveSchedulePauseJob;

class VehicleInactiveEventListener
{
    /**
     * Handle the event.
     *
     * @param VehicleInactiveEvent $event
     * @return void
     */
    public function handle(VehicleInactiveEvent $event)
    {
        dispatch(new VehicleActiveSchedulePauseJob($event->vehicle));
    }
}
