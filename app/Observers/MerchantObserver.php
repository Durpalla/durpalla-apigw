<?php

namespace App\Observers;

use Illuminate\Support\Facades\Cache;
use App\Models\Merchant;

class MerchantObserver
{
    public function __construct()
    {
        Cache::forget('merchant_dropdowns');
    }

    /**
     * Handle the merchant "created" event.
     *
     * @param  Merchant  $merchant
     * @return void
     */
    public function created(Merchant $merchant)
    {
        //
    }

    /**
     * Handle the merchant "updated" event.
     *
     * @param  Merchant  $merchant
     * @return void
     */
    public function updated(Merchant $merchant)
    {
        //
    }

    /**
     * Handle the merchant "deleted" event.
     *
     * @param  Merchant  $merchant
     * @return void
     */
    public function deleted(Merchant $merchant)
    {
        //
    }

    /**
     * Handle the merchant "restored" event.
     *
     * @param  Merchant  $merchant
     * @return void
     */
    public function restored(Merchant $merchant)
    {
        //
    }

    /**
     * Handle the merchant "force deleted" event.
     *
     * @param  Merchant  $merchant
     * @return void
     */
    public function forceDeleted(Merchant $merchant)
    {
        //
    }
}
