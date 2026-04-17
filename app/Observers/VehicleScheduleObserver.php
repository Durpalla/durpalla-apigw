<?php

namespace App\Observers;

use App\Jobs\SyncVehicleScheduleToOpenSearch;
use App\Models\VehicleSchedule;

class VehicleScheduleObserver
{
    public function saved(VehicleSchedule $schedule): void
    {
        SyncVehicleScheduleToOpenSearch::dispatch((int) $schedule->id);
    }

    public function deleted(VehicleSchedule $schedule): void
    {
        SyncVehicleScheduleToOpenSearch::dispatch((int) $schedule->id);
    }

    public function forceDeleted(VehicleSchedule $schedule): void
    {
        SyncVehicleScheduleToOpenSearch::dispatch((int) $schedule->id);
    }
}
