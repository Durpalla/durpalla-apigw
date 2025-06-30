<?php

namespace App\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use Laravel\Passport\Events\AccessTokenCreated;

class RevokeOldTokens
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
     * @param  AccessTokenCreated  $event
     * @return void
     */
    public function handle(AccessTokenCreated $event)
    {
        $user = User::where('id', $event->userId)->first();
        if($user && $user->hasAnyRole(['admin', 'supervisor', 'manager'])) {
            DB::table('oauth_access_tokens')
                ->where('id', '<>', $event->tokenId)
                ->where('user_id', $event->userId)
                ->where('client_id', $event->clientId)
                ->delete();
        }
    }
}
