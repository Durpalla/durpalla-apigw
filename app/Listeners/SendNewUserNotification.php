<?php

namespace App\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Notification;
use App\Notifications\UserCreatedNotification;
use App\Models\User;

class SendNewUserNotification
{
    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct()
    {

    }

    /**
     * Handle the event.
     *
     * @param object $event
     * @return void
     */
    public function handle(object $event)
    {
//        $admins = User::whereHas('roles', function ($query) {
//                $query->where('name', 'admin');
//            })->get();
//
//        Notification::send($admins, new UserCreatedNotification($event->user));
    }
}
