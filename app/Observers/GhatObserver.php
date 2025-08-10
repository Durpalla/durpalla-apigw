<?php

namespace App\Observers;

use Illuminate\Support\Facades\Cache;
use App\Jobs\GhatUpdateToFirebaseJob;
use App\Models\Ghat;
use App\Services\FirebaseService;
use App\Services\GhatService;

class GhatObserver
{
    private $firebase;
    public function __construct(FirebaseService $firebaseService, GhatService $ghatService)
    {
        $this->firebase = $firebaseService;
        Cache::forget('ghats');
//        dispatch(new GhatUpdateToFirebaseJob($this->firebase, $ghatService));
    }

    /**
     * Handle the ghat "created" event.
     *
     * @param  Ghat  $ghat
     * @return void
     */
    public function created(Ghat $ghat)
    {
        session()->flash('success', 'Ghat successfully created');
    }

    /**
     * Handle the ghat "updated" event.
     *
     * @param  Ghat  $ghat
     * @return void
     */
    public function updated(Ghat $ghat)
    {
        session()->flash('success', 'Ghat successfully update');
    }

    /**
     * Handle the ghat "deleted" event.
     *
     * @param  Ghat  $ghat
     * @return void
     */
    public function deleted(Ghat $ghat)
    {
        session()->flash('success', 'Ghat successfully deleted');
    }

    /**
     * Handle the ghat "restored" event.
     *
     * @param  Ghat  $ghat
     * @return void
     */
    public function restored(Ghat $ghat)
    {
        session()->flash('success', 'Ghat successfully restored');
    }

    /**
     * Handle the ghat "force deleted" event.
     *
     * @param  Ghat  $ghat
     * @return void
     */
    public function forceDeleted(Ghat $ghat)
    {
        session()->flash('success', 'Ghat permanently deleted');
    }
}
