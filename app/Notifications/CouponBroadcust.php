<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CouponBroadcust extends Notification implements ShouldQueue
{
    use Queueable;
    public $coupon;

    /**
     * Create a new notification instance.
     *
     * @return void
     */
    public function __construct( $coupon )
    {
        $this->coupon = $coupon;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function via($notifiable)
    {
        return ['mail', 'database'];
    }

    /**
     * Get the mail representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return \Illuminate\Notifications\Messages\MailMessage
     */
    public function toMail($notifiable)
    {
        $coupon_amount = ($this->coupon->discount_type == 'percent') ? $this->coupon->discount_amount . '%' : $this->coupon->discount_amount . 'Taka';
        return (new MailMessage)

                    ->subject(config('app.name') . ' Coupon')
                    ->greeting('Dear ' . $notifiable->name)
                    ->line('We have offer you a discount coupon, which you can enjoy within ' . date('d/m/Y', strtotime($this->coupon->offer_start)) . ' to ' . date('d/m/Y', strtotime($this->coupon->offer_end)))
                    ->line('Here is your coupon code ' . $this->coupon->code . ' You will enjoy ' . $coupon_amount . ' discount')
                    ->line('Thank you for using our ' . config('app.name') . ' service!');
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
            'type' => 'coupon',
            'label' => 'success',
            'property' => $this->coupon
        ];
    }
}
