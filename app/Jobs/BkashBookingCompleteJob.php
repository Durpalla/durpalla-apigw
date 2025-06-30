<?php

namespace App\Jobs;

use App\Constants\AppConst;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\PaymentLog;

class BkashBookingCompleteJob
{
    use Dispatchable, Queueable, InteractsWithQueue, SerializesModels;

    public $timeout = 120;
    public $backoff = 15;
    public $tries = 5;
    public $maxExceptions = 3;
    private $response;

    public function __construct($response)
    {
        $this->response = json_decode($response);
    }

    public function handle()
    {
        try {
            \Log::debug(json_encode($this->response));
            if(isset($this->response->transactionStatus) && $this->response->transactionStatus == AppConst::BKASH_PAYMENT_COMPLETED) {
                $booking = Booking::with(['payment', 'bookingItems'])->whereHas('payment', function ($query) {
                    $query->where('transaction_id', $this->response->merchantInvoiceNumber);
                })->first();

                $booking->update(['status' => AppConst::BOOKING_COMPLETE]);
                $booking->payment->update(['bank_tran_id' => $this->response->trxID, 'status' => AppConst::PAYMENT_SUCCESS, 'paid_amount' => $this->response->amount, 'payment_gateway' => 'Bkash', 'payment_method' => 'Bkash', 'store_amount' => $this->response->amount]);
                $booking->bookingItems->each(function($item, $key) {
                    $item->update(['status' => AppConst::BOOKING_ITEM_ACTIVE]);
                });

                PaymentLog::create([
                    'type' => AppConst::GATEWAY_TYPE_RESPONSE,
                    'booking_id' => $booking->id,
                    'payment_id' => $booking->payment->id,
                    'transaction_id' => $booking->payment->transaction_id,
                    'payment_method' => AppConst::PAYMENT_METHOD_BKASH,
                    'bank_transaction_id' => $this->response->trxID,
                    'data' => (array) $this->response
                ]);
            }

        } catch (\Exception $exception) {
            \Log::debug($exception);
        }
    }
}
