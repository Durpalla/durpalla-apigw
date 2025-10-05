<?php

namespace App\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use Laravel\Passport\Events\RefreshTokenCreated;

class PruneOldTokens
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
     * @param  RefreshTokenCreated  $event
     * @return void
     */
    public function handle(RefreshTokenCreated $event)
    {
        $user = User::where('id', $event->userId)->first();
        if($user) {
            DB::table('oauth_access_tokens')
                ->where('id', '<>', $event->tokenId)
                ->where('user_id', $event->userId)
                ->update(['revoked' => true]);
        }
    }
}
