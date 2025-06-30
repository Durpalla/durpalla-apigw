<?php

namespace Modules\BroadCast\Emails;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;
use Modules\BroadCast\Entities\BroadCast;

class BroadCastEmail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @var BroadCast
     */
    private $broadcast;
    private $body;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct(BroadCast $broadcast, $message)
    {
        $this->broadcast = $broadcast;
        $this->body = $message;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build(): BroadCastEmail
    {
        return $this->view('broadcast::emails.broadcast', ['body' => $this->body]);
    }
}
