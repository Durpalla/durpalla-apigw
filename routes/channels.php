<?php

use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
*/

Broadcast::channel('App.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('user.{toUserId}', function ($user, $toUserId) {
    return (int) $user->id === (int) $toUserId;
});

Broadcast::channel('New.Notification', function ($user, $post) {
    return (int) $user->id === (int) ($post->user_id ?? 0);
});

Broadcast::channel('notification.{userId}', function ($user, $userId) {
    return (int) $user->id === (int) $userId;
});
