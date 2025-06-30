<?php

namespace Modules\BroadCast\Jobs;

use App\Constants\AppConst;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\User;
use Modules\BroadCast\Entities\BroadCast;

class BroadCastCreatedJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @var BroadCast
     */
    private $broadcast;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(BroadCast $broadcast)
    {
        $this->broadcast = $broadcast;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $query = User::where(['type' => 'customer', 'status' => AppConst::CUSTOMER_ACTIVE])
            ->select('name', 'mobile', 'email', 'device_id');
        if($this->broadcast->group === 'individual' && $this->broadcast->customers !== null) {
            $query->whereIn('id', explode(',', $this->broadcast->customers));
        }

         $query->chunk(500, function($customers) {
             if($this->broadcast->type == 'topic') {
                dispatch( new BroadcastTopicJob($this->broadcast, $customers));
             } else {
                 $customers->each(function ($item, $key) {
                     dispatch(new BroadCastableJob($this->broadcast, $item));
                 });
             }
        });
    }
}
