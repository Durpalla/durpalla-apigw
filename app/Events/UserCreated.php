<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use App\Models\User;

class UserCreated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;
    public $user;
    public $type;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct(User $user, $type = 'web')
    {
        $this->user = $user;
        $this->type = $type;
    }
}
