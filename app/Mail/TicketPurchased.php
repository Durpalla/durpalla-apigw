<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class TicketPurchased extends Mailable
{
    use Queueable, SerializesModels;
    public $pdf;
    public $user;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct( $pdf, $user )
    {
        $this->pdf = $pdf;
        $this->user = $user;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build(): TicketPurchased
    {
        //setup the mail
        $mail = $this->subject('The subject', ['user' => $this->user])->view('emails.default');
        $mail->attachData($this->pdf->output(), 'invoice.pdf');

        //return and execute sending the mailable
        return $mail;
    }
}
