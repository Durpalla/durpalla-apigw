<?php

namespace App\Observers;

use App\Constants\AppConst;
use App\Events\WithdrawalActionEvent;
use App\Models\AgentWithdrawal;

class AgentWithdrawalObserver
{
    /**
     * Handle the agent withdrawal "created" event.
     *
     * @param  AgentWithdrawal  $agentWithdrawal
     * @return void
     */
    public function created(AgentWithdrawal $agentWithdrawal)
    {
        //
    }

    /**
     * Handle the agent withdrawal "updated" event.
     *
     * @param  AgentWithdrawal  $agentWithdrawal
     * @return void
     */
    public function updated(AgentWithdrawal $agentWithdrawal)
    {
        event(new WithdrawalActionEvent($agentWithdrawal));
    }

    /**
     * Handle the agent withdrawal "deleted" event.
     *
     * @param  AgentWithdrawal  $agentWithdrawal
     * @return void
     */
    public function deleted(AgentWithdrawal $agentWithdrawal)
    {
        //
    }

    /**
     * Handle the agent withdrawal "restored" event.
     *
     * @param  AgentWithdrawal  $agentWithdrawal
     * @return void
     */
    public function restored(AgentWithdrawal $agentWithdrawal)
    {
        //
    }

    /**
     * Handle the agent withdrawal "force deleted" event.
     *
     * @param  AgentWithdrawal  $agentWithdrawal
     * @return void
     */
    public function forceDeleted(AgentWithdrawal $agentWithdrawal)
    {
        //
    }
}
