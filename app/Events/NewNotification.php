<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Pusher\App\Facades\Pusher;

class NewNotification implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;
    public $notification;
    public $user;
    public $counter;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct($user, $notification)
    {
        $this->user = $user;
        $this->notification = $notification;
        $this->counter = ($user->unreadNotifications) ? $user->unreadNotifications->count() : 0;
    }

    public function broadcastWith()
    {
        // This must always be an array. Since it will be parsed with json_encode()
        return [
            'user' => $this->user->id,
            'notification' => $this->notification['message'],
            'title' => $this->notification['type'],
            'counter' => $this->counter
        ];
    }

    public function broadcastAs()
    {
        return 'newNotification';
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return Channel|array
     */
    public function broadcastOn()
    {
        return new Channel('notification');
        // return new PrivateChannel('user.'.$this->message->to);
    }
}
