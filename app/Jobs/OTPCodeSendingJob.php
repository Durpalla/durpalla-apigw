<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class OTPCodeSendingJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    public $timeout = 120;
    public $backoff = 3; //wait seconds for next try
    public $tries = 5;
    public $maxExceptions = 3;
    private $mobile;
    private $code;
    /**
     * Create a new job instance.
     *
     * @param $mobile
     * @param $code
     */
    public function __construct($mobile, $code)
    {
        $this->mobile = $mobile;
        $this->code = $code;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        sendSMS([
            'mobile' => $this->mobile,
            'message' => parseTemplate(getOption('sms_otp_template', config('app.name') . ' verification code is {code}'), ['code' => $this->code])
        ]);
    }
}
