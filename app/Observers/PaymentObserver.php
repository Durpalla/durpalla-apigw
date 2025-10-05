<?php

namespace App\Observers;

use App\Models\Payment;
use App\Models\PaymentCollector;

class PaymentObserver
{
    /**
     * Handle the payment "created" event.
     *
     * @param  Payment  $payment
     * @return void
     */
    public function created(Payment $payment)
    {
        if(auth()->user()->type == 'customer') {
            PaymentCollector::create([
                'booking_id' => $payment->booking_id,
                'payment_id' => $payment->id,
                'supervisor_id' => auth()->user()->id,
                'amount' => $payment->paid_amount,
                'payment_type' => $payment->payment_method,
                'remarks' => ($payment->booking->total_payable == $payment->paid_amount) ? 'Full payment' : 'Partial payment'
            ]);
        }
    }

    /**
     * Handle the payment "updated" event.
     *
     * @param  Payment  $payment
     * @return void
     */
    public function updated(Payment $payment)
    {
        //
    }

    /**
     * Handle the payment "deleted" event.
     *
     * @param  Payment  $payment
     * @return void
     */
    public function deleted(Payment $payment)
    {
        //
    }

    /**
     * Handle the payment "restored" event.
     *
     * @param  Payment  $payment
     * @return void
     */
    public function restored(Payment $payment)
    {
        //
    }

    /**
     * Handle the payment "force deleted" event.
     *
     * @param  Payment  $payment
     * @return void
     */
    public function forceDeleted(Payment $payment)
    {
        //
    }
}
