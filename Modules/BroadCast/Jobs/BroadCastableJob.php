<?php

namespace Modules\BroadCast\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Mail;
use App\User;
use Modules\BroadCast\Emails\BroadCastEmail;
use Modules\BroadCast\Entities\BroadCast;
use Modules\BroadCast\Notifications\BroadcastEmailNotification;
use Rajtika\Firebase\Services\Firebase;

class BroadCastableJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @var User
     */
    private $customer;
    /**
     * @var BroadCast
     */
    private $broadcast;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(BroadCast $broadcast, User $customer)
    {
        $this->broadcast = $broadcast;
        $this->customer = $customer;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $this->{$this->broadcast->type}();
    }

    private function sms()
    {
        sendSMS([
           'mobile' =>  $this->customer->mobile,
            'message' => $this->getMessage()
        ]);
    }

    private function email()
    {
        if ($this->customer->email !== null)
            Mail::to($this->customer->email)->send(new BroadCastEmail($this->broadcast, $this->getMessage()));
    }

    private function message()
    {
        //
    }

    private function notification()
    {
        if ($this->customer->email !== null)
            $this->customer->notify(new BroadcastEmailNotification($this->broadcast->title, $this->getMessage()));
    }

    private function fcm()
    {
        if($this->customer->device_id !== null && strlen($this->customer->device_id) > 30) {
            $firebase = Firebase::to($this->customer->device_id)
                ->setTitle($this->broadcast->title)
                ->setBody($this->getMessage());
            $this->broadcast->attachment !== null || $firebase->setImage(asset($this->broadcast->attachment));
            $firebase->send('data');
        }
    }

    private function all()
    {
        $this->sms();
        $this->email();
        $this->message();
        $this->fcm();
    }

    private function getMessage()
    {
        return parseTemplate($this->broadcast->message, $this->customer->toArray());
    }
}
