<?php

namespace Modules\Booking\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Models\CabinLock;

class BookingSessionReleaseJob
{
    use Dispatchable;

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        if(session()->has('user.carts')) {
            $ids = collect(session()->get('user.carts'))->where('type', '!=', 'deck')->pluck('item_id');
            $lockItems = CabinLock::whereIn('mapping_id', $ids)->get();
            if($lockItems) {
                $lockItems->each(function($item, $key) {
                    $item->delete();
                });
            }
            session()->put('user.carts', []);
        }
    }
}
