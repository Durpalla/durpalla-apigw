<?php

namespace App\Observers;

use Illuminate\Support\Facades\Cache;
use App\Models\Gateway;

class GatewayObserver
{
    public function __construct()
    {
        Cache::forget('gateways');
    }

    /**
     * Handle the gateway "created" event.
     *
     * @param  Gateway  $gateway
     * @return void
     */
    public function created(Gateway $gateway)
    {
        //
    }

    /**
     * Handle the gateway "updated" event.
     *
     * @param  Gateway  $gateway
     * @return void
     */
    public function updated(Gateway $gateway)
    {
        //
    }

    /**
     * Handle the gateway "deleted" event.
     *
     * @param  Gateway  $gateway
     * @return void
     */
    public function deleted(Gateway $gateway)
    {
        //
    }

    /**
     * Handle the gateway "restored" event.
     *
     * @param  Gateway  $gateway
     * @return void
     */
    public function restored(Gateway $gateway)
    {
        //
    }

    /**
     * Handle the gateway "force deleted" event.
     *
     * @param  Gateway  $gateway
     * @return void
     */
    public function forceDeleted(Gateway $gateway)
    {
        //
    }
}
