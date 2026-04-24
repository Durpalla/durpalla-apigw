<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Fired when profile fields are saved from the API. No mail/SMS is sent by default.
 */
class ProfileUpdate extends Notification
{
    use Queueable;

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return [];
    }
}
