<?php

namespace App\Observers;

use App\Constants\AppConst;
use Illuminate\Support\Facades\Cache;
use App\Models\Agent;
use App\Models\User;

class AgentObserver
{
    public function __construct()
    {
        Cache::forget('agents');
    }

    /**
     * Handle the agent "created" event.
     *
     * @param  Agent  $agent
     * @return void
     */
    public function created(Agent $agent)
    {
        $user = User::find($agent->id);
        $user->assignRole(AppConst::AGENT_ROLE);
    }

    /**
     * Handle the agent "updated" event.
     *
     * @param  Agent  $agent
     * @return void
     */
    public function updated(Agent $agent)
    {
        $agent->meta->update(request()->all());
        $agent->incentive->update(request()->all());
    }

    /**
     * Handle the agent "deleted" event.
     *
     * @param  Agent  $agent
     * @return void
     */
    public function deleted(Agent $agent)
    {
        //
    }

    /**
     * Handle the agent "restored" event.
     *
     * @param  Agent  $agent
     * @return void
     */
    public function restored(Agent $agent)
    {
        //
    }

    /**
     * Handle the agent "force deleted" event.
     *
     * @param  Agent  $agent
     * @return void
     */
    public function forceDeleted(Agent $agent)
    {
        //
    }
}
