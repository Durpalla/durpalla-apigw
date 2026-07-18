<?php

namespace App\Jobs;

use App\Models\Merchant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Notifications\UserCreatedNotification;

class UserCreatedJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    public $timeout = 120;
    public $backoff = 15;
    public $tries = 5;
    public $maxExceptions = 3;

    private Authenticatable $user;

    public function __construct(Authenticatable $user)
    {
        $this->user = $user;
    }

    public function handle()
    {
        if ($this->user instanceof Merchant) {
            return;
        }

        if (method_exists($this->user, 'notify')) {
            $this->user->notify(new UserCreatedNotification());
        }
    }
}
