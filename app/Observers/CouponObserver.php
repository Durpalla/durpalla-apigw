<?php

namespace App\Observers;

use Illuminate\Support\Facades\Cache;
use App\Models\Coupon;
use App\Jobs\CouponUpdateToFirebaseJob;
use App\Services\FirebaseService;

class CouponObserver
{
    private $firebase;
    public function __construct(FirebaseService $firebaseService)
    {
        $this->firebase = $firebaseService;
        Cache::forget('coupons');
        dispatch(new CouponUpdateToFirebaseJob($firebaseService));
    }

    /**
     * Handle the coupon "created" event.
     *
     * @param  Coupon  $coupon
     * @return void
     */
    public function created(Coupon $coupon)
    {
        session()->flash('success', 'Coupon successfully created');
    }

    /**
     * Handle the coupon "updated" event.
     *
     * @param  Coupon  $coupon
     * @return void
     */
    public function updated(Coupon $coupon)
    {
        session()->flash('success', 'Coupon successfully updated');
    }

    /**
     * Handle the coupon "deleted" event.
     *
     * @param  Coupon  $coupon
     * @return void
     */
    public function deleted(Coupon $coupon)
    {
        session()->flash('success', 'Coupon successfully deleted');
    }

    /**
     * Handle the coupon "restored" event.
     *
     * @param  Coupon  $coupon
     * @return void
     */
    public function restored(Coupon $coupon)
    {
        session()->flash('success', 'Coupon successfully restored');
    }

    /**
     * Handle the coupon "force deleted" event.
     *
     * @param  Coupon  $coupon
     * @return void
     */
    public function forceDeleted(Coupon $coupon)
    {
        session()->flash('success', 'Coupon permanently deleted');
    }
}
