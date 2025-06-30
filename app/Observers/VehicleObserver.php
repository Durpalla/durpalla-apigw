<?php

namespace App\Observers;

use Illuminate\Support\Facades\Cache;
use App\Jobs\VehicleListUpdateToFirebase;
use App\Models\Vehicle as Vehicle;
use App\Services\FirebaseService;
use App\Models\VehicleRouteMapping;

class VehicleObserver
{
    public function __construct(FirebaseService $firebase)
    {
        Cache::forget('vehicles');
        dispatch(new VehicleListUpdateToFirebase($firebase));
    }

    /**
     * Handle the vehicle "created" event.
     *
     * @param  Vehicle  $vehicle
     * @return void
     */
    public function created(Vehicle $vehicle)
    {
        session()->flash('success', 'New ' . $vehicle->vehicle_type . ' created successfully');
        VehicleRouteMapping::create(['vehicle_id' => $vehicle->id, 'route_id' => $vehicle->route_id, 'assigned_by' => $vehicle->user_id, 'merchant_id' => $vehicle->merchant_id]);
    }

    /**
     * Handle the vehicle "updated" event.
     *
     * @param  Vehicle  $vehicle
     * @return void
     */
    public function updated(Vehicle $vehicle)
    {
        session()->flash('success', ucfirst($vehicle->vehicle_type) . ' created successfully');
        VehicleRouteMapping::create(['vehicle_id' => $vehicle->id, 'route_id' => $vehicle->route_id, 'assigned_by' => auth()->user()->id, 'merchant_id' => $vehicle->merchant_id]);
    }

    /**
     * Handle the vehicle "deleted" event.
     *
     * @param  Vehicle  $vehicle
     * @return void
     */
    public function deleted(Vehicle $vehicle)
    {
        session()->flash('success', ucfirst($vehicle->vehicle_type) . ' deleted successfully');
    }

    /**
     * Handle the vehicle "restored" event.
     *
     * @param  Vehicle  $vehicle
     * @return void
     */
    public function restored(Vehicle $vehicle)
    {
        session()->flash('success', ucfirst($vehicle->vehicle_type) . ' restored successfully');
    }

    /**
     * Handle the vehicle "force deleted" event.
     *
     * @param  Vehicle  $vehicle
     * @return void
     */
    public function forceDeleted(Vehicle $vehicle)
    {
        session()->flash('success', ucfirst($vehicle->vehicle_type) . ' permanently deleted');
    }
}
