<?php

namespace App\Observers;

use Illuminate\Support\Facades\Cache;
use App\Models\CabinType;

class CabinTypeObserver
{
    public function __construct()
    {
        Cache::forget('cabin_types');
    }

    /**
     * Handle the cabin type "created" event.
     *
     * @param  CabinType  $cabinType
     * @return void
     */
    public function created(CabinType $cabinType)
    {
        session()->flash('success', ucfirst($cabinType->type) . ' type successfully created');
    }

    /**
     * Handle the cabin type "updated" event.
     *
     * @param  CabinType  $cabinType
     * @return void
     */
    public function updated(CabinType $cabinType)
    {
        session()->flash('success', ucfirst($cabinType->type) . ' type successfully created');
    }

    /**
     * Handle the cabin type "deleted" event.
     *
     * @param  CabinType  $cabinType
     * @return void
     */
    public function deleted(CabinType $cabinType)
    {
        session()->flash('success', ucfirst($cabinType->type) . ' type successfully deleted');
    }

    /**
     * Handle the cabin type "restored" event.
     *
     * @param  CabinType  $cabinType
     * @return void
     */
    public function restored(CabinType $cabinType)
    {
        session()->flash('success', ucfirst($cabinType->type) . ' type successfully restored');
    }

    /**
     * Handle the cabin type "force deleted" event.
     *
     * @param  CabinType  $cabinType
     * @return void
     */
    public function forceDeleted(CabinType $cabinType)
    {
        session()->flash('success', ucfirst($cabinType->type) . ' type permanently deleted');
    }
}
