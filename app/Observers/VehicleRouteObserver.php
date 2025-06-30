<?php

namespace App\Observers;

use Illuminate\Support\Facades\Cache;
use App\Jobs\GhatUpdateToFirebaseJob;
use App\Services\FirebaseService;
use App\Services\GhatService;
use App\Models\VehicleRoute;

class VehicleRouteObserver
{
    private $firebase;
    private $stoppage;
    public function __construct(
        FirebaseService $firebaseService,
        GhatService $ghatService
    )
    {
        $this->firebase = $firebaseService;
        $this->stoppage = $ghatService;
        dispatch(new GhatUpdateToFirebaseJob($this->firebase, $this->stoppage));
        Cache::forget('ghats');
    }
    /**
     * Handle the vehicle route "created" event.
     *
     * @param  VehicleRoute  $vehicleRoute
     * @return void
     */
    public function created(VehicleRoute $vehicleRoute)
    {
        //
    }

    /**
     * Handle the vehicle route "updated" event.
     *
     * @param  VehicleRoute  $vehicleRoute
     * @return void
     */
    public function updated(VehicleRoute $vehicleRoute)
    {
        //
    }

    /**
     * Handle the vehicle route "deleted" event.
     *
     * @param  VehicleRoute  $vehicleRoute
     * @return void
     */
    public function deleted(VehicleRoute $vehicleRoute)
    {
        //
    }

    /**
     * Handle the vehicle route "restored" event.
     *
     * @param  VehicleRoute  $vehicleRoute
     * @return void
     */
    public function restored(VehicleRoute $vehicleRoute)
    {
        //
    }

    /**
     * Handle the vehicle route "force deleted" event.
     *
     * @param  VehicleRoute  $vehicleRoute
     * @return void
     */
    public function forceDeleted(VehicleRoute $vehicleRoute)
    {
        //
    }
}
