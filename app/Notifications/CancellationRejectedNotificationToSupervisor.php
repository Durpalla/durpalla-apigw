<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Rajtika\Firebase\Services\Firebase;

class CancellationRejectedNotificationToSupervisor extends Notification implements ShouldQueue
{
    use Queueable;

    protected $cancellation;

    /**
     * Create a new notification instance.
     *
     * @return void
     */
    public function __construct($bookingCancellation)
    {
        $this->cancellation = $bookingCancellation;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param mixed $notifiable
     * @return array
     */
    public function via($notifiable)
    {
        return ['mail'];
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
            ->subject('Booking cancellation reject')
            ->greeting('Dear ' . $notifiable->name)
            ->line('Your booking (PNR:' . $notifiable->cancellation->booking_id . ') cancellation request rejected');
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
            //
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
