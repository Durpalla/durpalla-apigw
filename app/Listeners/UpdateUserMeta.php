<?php

namespace App\Listeners;

use App\Events\UserCreated;
use App\Models\UserMeta;
use Illuminate\Support\Facades\Auth;

class UpdateUserMeta
{
    public function handle(UserCreated $event)
    {
        $meta = UserMeta::firstOrNew([
            'user_id' => $event->user->id,
        ]);
        $meta->created_by = ($event->type == 'office') ? 'office' : 'self';
        $meta->platform = $event->type;
        if ($event->type == 'office' && Auth::check()) {
            $meta->officer_id = Auth::user()->id;
            if (isset(Auth::user()->designation) && is_object(Auth::user()->designation)) {
                $meta->designation = Auth::user()->designation['name'] ?? null;
            }
        }

        $meta->save();
    }
}
