<?php

namespace App\Channels;

use Illuminate\Notifications\Notification;
use App\Services\SmsService;

class SmsChannel
{
    public function send($notifiable, Notification $notification): void
    {
        $params = $notification->toSms($notifiable);
        $data = ['status' => false, 'message' => 'Pending'];
        (new SmsService())->send($notifiable->customer, $params['message'], $params['mobile'], $data);
    }
}
