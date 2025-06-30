<?php

namespace Modules\Payment\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Models\Payment;

class SslcommerzPaymentInitiatedJob
{
    use Dispatchable;

    /**
     * @var Payment
     */
    private $payment;
    private $sessionID;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(Payment $payment, $gatewaySessionId)
    {
        $this->payment = $payment;
        $this->sessionID = $gatewaySessionId;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $this->payment->update(['payment_gateway' => 'sslcommerz', 'gateway_session' => $this->sessionID]);
    }
}
