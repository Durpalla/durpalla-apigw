<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\Coupon;
use App\Services\FirebaseService;

class CouponUpdateToFirebaseJob
{
    use Dispatchable, Queueable, InteractsWithQueue, SerializesModels;
    public $timeout = 120;
    public $backoff = 15;
    public $tries = 5;
    public $maxExceptions = 3;
    private $firebase;

    /**
     * Create a new job instance.
     *
     * @param FirebaseService $firebaseService
     */
    public function __construct(FirebaseService $firebaseService)
    {
        $this->firebase = $firebaseService;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $coupons = Coupon::select('poster')
            ->where('is_offer', 1)
            ->where('offer_start', '>=', date('Y-m-d'))
//            ->where('poster', '!=', null)
            ->take(6)->get();
        $this->firebase->set('offers')
            ->update($coupons->map(function ($item) {
                $item->thumbnail = asset($item->poster);
                $item->poster = asset($item->poster);
                return $item;
            }));
    }
}
