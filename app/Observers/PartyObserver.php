<?php

namespace App\Observers;

use Illuminate\Support\Facades\Cache;
use App\Models\Party;

class PartyObserver
{
    public function __construct()
    {
        Cache::forget('parties');
        Cache::forget('party_dropdowns');
    }

    /**
     * Handle the party "created" event.
     *
     * @param  Party  $party
     * @return void
     */
    public function created(Party $party)
    {
        //
    }

    /**
     * Handle the party "updated" event.
     *
     * @param  Party  $party
     * @return void
     */
    public function updated(Party $party)
    {
        //
    }

    /**
     * Handle the party "deleted" event.
     *
     * @param  Party  $party
     * @return void
     */
    public function deleted(Party $party)
    {
        //
    }

    /**
     * Handle the party "restored" event.
     *
     * @param  Party  $party
     * @return void
     */
    public function restored(Party $party)
    {
        //
    }

    /**
     * Handle the party "force deleted" event.
     *
     * @param  Party  $party
     * @return void
     */
    public function forceDeleted(Party $party)
    {
        //
    }
}
