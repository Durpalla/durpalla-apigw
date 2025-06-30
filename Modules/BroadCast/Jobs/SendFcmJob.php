<?php

namespace Modules\BroadCast\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\User;
use Rajtika\Firebase\Services\Firebase;

class SendFcmJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @var User
     */
    private $user;
    /**
     * @var mixed|string
     */
    private $message;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(User $user, $message = '')
    {
        $this->user = $user;
        $this->message = $message;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        if(strlen($this->user->device_id) > 32) {
            Firebase::to($this->user->device_id)
                ->setType('notification')
                ->setTitle(config('app.name') . " partner")
                ->setBody('Dear ' . $this->user->name . ', We have received your partner request. We will call you within 24 hours. Thank you for stay with us')
                ->send('data');
        }
    }
}
