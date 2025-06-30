<?php


namespace Modules\BroadCast\Observer;


use Modules\BroadCast\Entities\BroadCast;
use Modules\BroadCast\Jobs\BroadCastCreatedJob;

class BroadCastObserver
{
    public function created(BroadCast $broadCast)
    {
        if($broadCast->scheduled_at !== null && $broadCast->scheduled_at > now()) {
            $scheduled = date('Y-m-d H:i:s', strtotime($broadCast->scheduled_at));
            dispatch(new BroadCastCreatedJob($broadCast))->delay($scheduled);
        } else {
            dispatch(new BroadCastCreatedJob($broadCast));
        }
    }

    public function updated(BroadCast $broadCast)
    {
        //
    }

    public function deleted(BroadCast $broadCast)
    {
        //
    }
}
