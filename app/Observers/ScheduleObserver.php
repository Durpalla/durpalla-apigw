<?php

namespace App\Observers;

use App\Constants\AppConst;
use App\Jobs\ScheduleCreatedJob;
use App\Jobs\VehicleActiveOnScheduleActiveJob;
use App\Models\VehicleSchedule;

class ScheduleObserver
{
    /**
     * Handle the vehicle schedule "created" event.
     *
     * @param  VehicleSchedule  $vehicleSchedule
     * @return void
     */
    public function created(VehicleSchedule $vehicleSchedule)
    {
        dispatch(new ScheduleCreatedJob($vehicleSchedule));
    }

    /**
     * Handle the vehicle schedule "updated" event.
     *
     * @param  VehicleSchedule  $vehicleSchedule
     * @return void
     */
    public function updated(VehicleSchedule $vehicleSchedule)
    {
        if($vehicleSchedule->status === AppConst::SCHEDULE_ACTIVE) {
            dispatch(new VehicleActiveOnScheduleActiveJob($vehicleSchedule));
        }
    }

    /**
     * Handle the vehicle schedule "deleted" event.
     *
     * @param  VehicleSchedule  $vehicleSchedule
     * @return void
     */
    public function deleted(VehicleSchedule $vehicleSchedule)
    {
        //
    }

    /**
     * Handle the vehicle schedule "restored" event.
     *
     * @param  VehicleSchedule  $vehicleSchedule
     * @return void
     */
    public function restored(VehicleSchedule $vehicleSchedule)
    {
        //
    }

    /**
     * Handle the vehicle schedule "force deleted" event.
     *
     * @param  VehicleSchedule  $vehicleSchedule
     * @return void
     */
    public function forceDeleted(VehicleSchedule $vehicleSchedule)
    {
        //
    }
}
