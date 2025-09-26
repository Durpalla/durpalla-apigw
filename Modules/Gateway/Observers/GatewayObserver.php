<?php

namespace Modules\Gateway\Observers;


use Illuminate\Support\Facades\Cache;
use Modules\Gateway\Entities\Gateway;

class GatewayObserver
{
    public function __construct()
    {
        Cache::forget('gateways');
    }

    public function created(Gateway $gateway)
    {
        //
    }

    public function updated(Gateway $gateway)
    {
        //
    }

    public function deleted(Gateway $gateway)
    {
        //
    }
}
