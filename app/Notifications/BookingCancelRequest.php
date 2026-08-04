<?php

namespace App\Notifications;

use App\Channels\FcmChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BookingCancelRequest extends Notification implements ShouldQueue
{
    use Queueable;

    public $cancellation;

    /**
     * Create a new notification instance.
     *
     * @param  mixed  $cancellation
     */
    public function __construct($cancellation)
    {
        $this->cancellation = $cancellation;
    }

    /**
     * @param  mixed  $notifiable
     * @return array<int, class-string|string>
     */
    public function via($notifiable): array
    {
        return ['database', 'mail', FcmChannel::class];
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Booking cancellation')
            ->greeting('Dear '.$notifiable->name.',')
            ->line('Your booking (PNR:'.$this->cancellation->booking_id.') cancellation request has been sent successfully');
    }

    /**
     * @param  mixed  $notifiable
     * @return array<string, mixed>
     */
    public function toArray($notifiable): array
    {
        return [
            'name' => $notifiable->name,
            'email' => $notifiable->email,
            'type' => 'cancellation_request',
            'label' => 'info',
            'property' => $this->cancellation,
        ];
    }

    /**
     * @param  mixed  $notifiable
     * @return array<string, mixed>|null
     */
    public function toFcm($notifiable): ?array
    {
        $token = (string) ($notifiable->device_id ?? '');
        if (strlen($token) <= 30) {
            return null;
        }

        return [
            'token' => $token,
            'notification' => [
                'title' => 'Cancellation request received',
                'body' => 'Your booking cancellation request has been sent successfully',
            ],
            'data' => [
                'type' => 'cancellation_request',
                'booking_id' => (string) $this->cancellation->booking_id,
            ],
        ];
    }
}
