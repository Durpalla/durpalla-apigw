<?php

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Here you may register all of the event broadcasting channels that your
| application supports. The given channel authorization callbacks are
| used to check if an authenticated user can listen to the channel.
|
*/

Broadcast::channel('App.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('user.{toUserId}', function ($user, $toUserId) {
    return $user->id == $toUserId;
});

Broadcast::channel('New.Notification', function ($post) {
    // return (int) auth()->user()->id != (int) $post->user_id;
    return true;
});
Broadcast::channel('notification', function ($notification) {
	dd('test');
    return true;
});