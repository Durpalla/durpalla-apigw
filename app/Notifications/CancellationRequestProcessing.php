<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Rajtika\Firebase\Services\Firebase;

class CancellationRequestProcessing extends Notification implements ShouldQueue
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
    public function via($notifiable)
    {
        return ['database'];
    }

    /**
     * Get the mail representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return \Illuminate\Notifications\Messages\MailMessage
     */
    public function toMail($notifiable)
    {
        return (new MailMessage)
            ->subject('Booking cancellation')
            ->greeting('Dear ' . $notifiable->name)
            ->line('Your cancellation request now under processing');
    }

    /**
     * Get the array representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function toArray($notifiable)
    {
        return [
            'name' => $notifiable->name,
            'email' => $notifiable->email,
            'type' => 'cancellation_processing',
            'label' => 'info',
            'property' => $this->cancellation
        ];
    }

    public function toFcm($notifiable)
    {
        if( strlen($notifiable->device_id) > 30 ) {
            return Firebase::to($notifiable->device_id)
                ->setType('booking')
                ->setID($this->cancellation->booking_id)
                ->setTitle('Cancellation request processing')
                ->setBody('Your booking cancellation request is in processing')
                ->send('data');
        }
        return false;
    }
}
