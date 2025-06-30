<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewMerchantNotify extends Notification implements ShouldQueue
{
    use Queueable;
    protected $merchant;
    /**
     * Create a new notification instance.
     *
     * @return void
     */
    public function __construct( $merchant )
    {
        $this->merchant = $merchant;
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
            ->subject('Merchant account creation')
            ->line('Welcome to ' . config('app.name'))
            ->line('Your merchant account has been created successfully.')
            ->line('If you have any query or comments please contact our support center.')
            ->line('Thank you')
            ->line(config('app.name'));
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
            'type' => 'merchant_created',
            'label' => 'info',
            'property' => $this->merchant
        ];
    }
}
