<?php

namespace App\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use App\Events\UserCreated;
use Mail;

class SendWelcomeEmail
{
    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     *
     * @param UserCreated $event
     * @return void
     */
    public function handle(UserCreated $event)
    {
        $data = array('name' => $event->user->name, 'email' => $event->user->email, 'body' => 'Welcome to jolzan. Hope you will enjoy our services.');

//        Mail::send('emails.mail', $data, function($message) use ($data) {
//            $message->to($data['email'])
//                    ->subject('Welcome to jolzan');
//            $message->from('noreply@jolzan.com');
//        });
    }
}
