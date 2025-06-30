<?php

namespace App\Observers;

use Illuminate\Support\Facades\Cache;
use App\Jobs\SupervisorUpdateToFirebaseJob;
use App\Services\FirebaseService;
use App\Models\VehicleSupervisor;

class SupervisorObserver
{
    private $firebase;
    public function __construct(FirebaseService $firebaseService)
    {
        $this->firebase = $firebaseService;
        Cache::forget('supervisors');
        dispatch(new SupervisorUpdateToFirebaseJob($firebaseService));
    }

    /**
     * Handle the vehicle supervisor "created" event.
     *
     * @param  VehicleSupervisor  $vehicleSupervisor
     * @return void
     */
    public function created(VehicleSupervisor $vehicleSupervisor)
    {
        //
    }

    /**
     * Handle the vehicle supervisor "updated" event.
     *
     * @param  VehicleSupervisor  $vehicleSupervisor
     * @return void
     */
    public function updated(VehicleSupervisor $vehicleSupervisor)
    {
        //
    }

    /**
     * Handle the vehicle supervisor "deleted" event.
     *
     * @param  VehicleSupervisor  $vehicleSupervisor
     * @return void
     */
    public function deleted(VehicleSupervisor $vehicleSupervisor)
    {
        //
    }

    /**
     * Handle the vehicle supervisor "restored" event.
     *
     * @param  VehicleSupervisor  $vehicleSupervisor
     * @return void
     */
    public function restored(VehicleSupervisor $vehicleSupervisor)
    {
        //
    }

    /**
     * Handle the vehicle supervisor "force deleted" event.
     *
     * @param  VehicleSupervisor  $vehicleSupervisor
     * @return void
     */
    public function forceDeleted(VehicleSupervisor $vehicleSupervisor)
    {
        //
    }
}
