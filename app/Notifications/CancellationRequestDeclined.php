<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Rajtika\Firebase\Services\Firebase;

class CancellationRequestDeclined extends Notification implements ShouldQueue
{
    use Queueable;

    public $cancellation;

    /**
     * Create a new notification instance.
     *
     * @return void
     */
    public function __construct($cancellation)
    {
        $this->cancellation = $cancellation;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param mixed $notifiable
     * @return array
     */
    public function via($notifiable)
    {
        return ['database', 'fcm'];
    }

    /**
     * Get the mail representation of the notification.
     *
     * @param mixed $notifiable
     * @return \Illuminate\Notifications\Messages\MailMessage
     */
    public function toMail($notifiable)
    {
        return (new MailMessage)
            ->subject('Booking cancellation')
            ->greeting('Dear ' . $notifiable->name)
            ->line('Your cancellation request has been declined.');
    }

    /**
     * Get the array representation of the notification.
     *
     * @param mixed $notifiable
     * @return array
     */
    public function toArray($notifiable)
    {
        return [
            'name' => $notifiable->name,
            'email' => $notifiable->email,
            'type' => 'cancellation_declined',
            'label' => 'danger',
            'property' => $this->cancellation
        ];
    }

    public function toFcm($notifiable)
    {
        if (strlen($notifiable->device_id) > 30) {
            return Firebase::to($notifiable->device_id)
                ->setID($this->cancellation->booking_id)
                ->setTitle('Cancellation request approved')
                ->setBody('Your booking cancellation request has been approved')
                ->send();
        }
        return false;
    }
}
