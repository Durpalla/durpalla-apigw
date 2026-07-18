<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UserCreated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $user;
    public $type;

    /**
     * @param  Authenticatable  $user  User, Customer, or other authenticatable
     */
    public function __construct(Authenticatable $user, $type = 'web')
    {
        $this->user = $user;
        $this->type = $type;
    }
}
