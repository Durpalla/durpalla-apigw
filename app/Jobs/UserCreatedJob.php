<?php

namespace App\Jobs;

use App\Models\Merchant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Notifications\NewMerchantNotify;
use App\Notifications\UserCreatedNotification;
use App\Models\User;

class UserCreatedJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    public $timeout = 120;
    public $backoff = 15;
    public $tries = 5;
    public $maxExceptions = 3;

    private $user;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct( User $user)
    {
        $this->user = $user;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        if($this->user->hasRole('merchant')) {
            $merchant = Merchant::where('user_id', $this->user->id)->first();
            $this->user->notify(new NewMerchantNotify($merchant));
        } else {
            $this->user->notify(new UserCreatedNotification());
        }
    }
}
