<?php

namespace App\Observers;

use App\Constants\AppConst;
use Illuminate\Support\Facades\Cache;
use App\Models\Partner;
use App\Models\User;

class PartnerObserver
{
    public function __construct()
    {
        Cache::forget('agents');
    }

    /**
     * Handle the agent "created" event.
     *
     * @param  Partner  $partner
     * @return void
     */
    public function created(Partner $partner)
    {
        $user = User::find($partner->id);
        $user->assignRole(AppConst::PARTNER_ROLE);
    }

    /**
     * Handle the agent "updated" event.
     *
     * @param  Partner  $partner
     * @return void
     */
    public function updated(Partner $partner)
    {
        $partner->meta->update(request()->all());
        $partner->incentive->update(request()->all());
    }

    /**
     * Handle the agent "deleted" event.
     *
     * @param  Partner  $partner
     * @return void
     */
    public function deleted(Partner $partner)
    {
        //
    }

    /**
     * Handle the agent "restored" event.
     *
     * @param  Partner  $partner
     * @return void
     */
    public function restored(Partner $partner)
    {
        //
    }

    /**
     * Handle the agent "force deleted" event.
     *
     * @param  Partner  $partner
     * @return void
     */
    public function forceDeleted(Partner $partner)
    {
        //
    }
}
