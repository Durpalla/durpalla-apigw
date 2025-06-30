<?php

namespace App\Listeners;

use App\Constants\AppConst;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use App\Events\WithdrawalActionEvent;
use App\Jobs\WithdrawalBalanceAdjustmentJob;
use App\Jobs\WithdrawalCanceledJob;
use App\Jobs\WithdrawalCompleteJob;

class WithdrawalActionListener
{
    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     *
     * @param  object  $event
     * @return void
     */
    public function handle(WithdrawalActionEvent $event)
    {
        if((int) $event->withdrawal->status === AppConst::WITHDRAWAl_COMPLETE) {
            dispatch(new WithdrawalBalanceAdjustmentJob($event->withdrawal));
            dispatch(new WithdrawalCompleteJob($event->withdrawal));
        } elseif((int) $event->withdrawal->status === AppConst::WITHDRAWAl_CANCELLED) {
            dispatch(new WithdrawalCanceledJob($event->withdrawal));
        }
    }
}
