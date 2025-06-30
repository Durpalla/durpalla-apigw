<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Rajtika\Firebase\Services\Firebase;

class BookingCancelRequest extends Notification implements ShouldQueue
{
    use Queueable;
    public $cancellation;

    /**
     * Create a new notification instance.
     *
     * @return void
     */
    public function __construct( $cancellation )
    {
        $this->cancellation = $cancellation;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function via($notifiable): array
    {
        return ['database', 'mail', 'fcm'];
    }

    /**
     * Get the mail representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return MailMessage
     */
    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
                    ->subject('Booking cancellation')
                    ->greeting('Dear ' . $notifiable->name . ',')
                    ->line('Your booking (PNR:' . $this->cancellation->booking_id . ') cancellation request has been sent successfully');
    }

    /**
     * Get the array representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function toArray($notifiable): array
    {
        return [
            'name' => $notifiable->name,
            'email' => $notifiable->email,
            'type' => 'cancellation_request',
            'label' => 'info',
            'property' => $this->cancellation
        ];
    }

    public function toFcm($notifiable)
    {
        if( strlen($notifiable->device_id) > 30 ) {
            return Firebase::to($notifiable->device_id)
                ->setID($this->cancellation->booking_id)
                ->setTitle('Cancellation request received')
                ->setBody('Your booking cancellation request has been sent successfully')
                ->send();
        }
        return false;
    }
}
