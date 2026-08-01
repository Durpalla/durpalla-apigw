<?php
namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class EmailOtp extends Notification implements ShouldQueue
{
    use Queueable;
    protected $code;

    /**
     * Create a new notification instance.
     *
     * @return void
     */
    public function __construct( $code )
    {
        $this->code = $code;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function via($notifiable)
    {
        return ['mail'];
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
            ->greeting('Dear ' . $notifiable->name )
            ->subject(config('app.name') . ' verification code')
            ->line('Your verification code is ' . $this->code)
            ->line('Enter this code to finish signing in or enabling two-factor authentication. It expires in 15 minutes.')
            ->line('If you did not request this, you can ignore this email.');
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
            'otp' => $this->code
        ];
    }
}
