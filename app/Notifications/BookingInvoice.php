<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Rajtika\Firebase\Services\Firebase;

class BookingInvoice extends Notification implements ShouldQueue
{
    use Queueable;
    protected $invoice;
    /**
     * Create a new notification instance.
     *
     * @return void
     */
    public function __construct( $invoice )
    {
        $this->invoice = $invoice;
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
        return (new MailMessage)
            ->greeting('Dear ' . $notifiable->name )
            ->subject('Booking Invoice')
            ->line('Thank your for completed your bookings. Your payment has confirmed.')
            ->line('Please see the attachment below and print it for your reference.')
            ->line('Thank you')

            ->line(config('app.name'));
            // ->attach($this->invoice->output(), "invoice.pdf");
            // ->attach($this->invoice->output(),[
            //     'as' => 'invoice.pdf',
            //     'mime' => 'application/pdf',
            // ]);
            // ->attach(view_path('emails.invoice'),[
            //         'as' => 'ab.pdf',
            //         'mime' => 'application/pdf',
            //         ]);
            // ->attachData($this->invoice->output(), 'invoice.pdf', [
            //     'mime' => 'application/pdf',
            // ]);
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
            'type' => 'invoice',
            'label' => 'info',
            'property' => $this->invoice
        ];
    }

    public function toFcm($notifiable)
    {
        if( strlen($notifiable->device_id) > 30 ) {
            return Firebase::to($notifiable->device_id)
                ->setID($this->invoice->id)
                ->setTitle('Your invoice ready')
                ->setBody('Your booking invoice ready, you will get it soon')
                ->send();
        }
        return false;
    }
}
