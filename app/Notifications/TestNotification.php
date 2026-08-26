<?php

namespace App\Notifications;

use AllowDynamicProperties;
use App\Helpers\CommonHelper;
use App\Models\Customer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;

#[AllowDynamicProperties]
class TestNotification extends Notification
{
    use Queueable;

    public function __construct(Customer $customer)
    {
        Log::info('TestNotification: Constructor called');
        $this->customer = $customer;
    }

    public function via($notifiable): array
    {
        Log::info('TestNotification: via called');
        $channels = CommonHelper::getNotificationChannels();
        Log::info('TestNotification: channels', ['channels' => $channels]);
        return $channels;
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->line('The introduction to the notification.')
            ->action('Notification Action', url('/'))
            ->line('Thank you for using our application!');
    }

    public function toSms($notifiable): ?array
    {
        return [
            'from' => getOption('notification_sms_from'),
            'mobile' => $notifiable->mobile,
            'message' => CommonHelper::parseTemplate(CommonHelper::strtoln(getOption('activation_template')), [
                'id' => $notifiable->customer->id,
                'name' => $notifiable->name,
                'customerID' => $this->customer->customerID,
                'due' => $this->customer->bill->dues,
                'package' => $this->customer->package->name ?? '---',
                'due_date' => date('d/m/Y', strtotime($this->customer->extended_due_date))
            ])
        ];
    }

    public function toFcm($notifiable): ?array
    {
        Log::info('TestNotification: toFcm called');
        return [
            'token' => (string) ($this->customer->device_id ?? $notifiable->device_id ?? ''),
            'platform' => 'android',
            'notification' => [
                'title' => 'Test Notification',
                'body' => 'This is just test notification.'
            ],
            'data' => [
                'is_reminder' => 'false'
            ]
        ];
    }

    public function toArray($notifiable): array
    {
        return [
            //
        ];
    }
}
