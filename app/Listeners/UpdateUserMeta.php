<?php

namespace App\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Mail;
use Auth;

class UpdateUserMeta
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
    public function handle(UserCreated $event)
    {
        $meta = \App\UserMeta::firstOrNew([
            'user_id' => $event->user->id
        ]);
        $meta->created_by = ( $event->type == 'office' ) ? 'office' : 'self';
        $meta->platform = $event->type;
        if( $event->type == 'office' && Auth::check()) {
            $meta->officer_id = Auth::user()->id;
            $meta->designation = Auth::user()->designation['name'];
        }

        $meta->save();
    }
}