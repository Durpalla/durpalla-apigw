<?php

namespace App\Jobs;

use App\Models\Booking;
use App\Services\MerchantBookingPaymentService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\View;

class SendPaymentLinkJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 60;

    public $backoff = 15;

    public $tries = 3;

    private int $bookingId;

    public function __construct($booking)
    {
        $this->bookingId = $booking instanceof Booking ? (int) $booking->id : (int) $booking;
    }

    public function handle(): void
    {
        $booking = Booking::with('customer')->find($this->bookingId);
        if (! $booking || ! $booking->payment_token || ! $booking->customer) {
            return;
        }

        $payUrl = MerchantBookingPaymentService::publicPayUrl((string) $booking->payment_token);
        $amount = number_format((float) $booking->total_payable, 2);
        $currency = function_exists('getOption') ? getOption('currency', 'Tk.') : 'Tk.';
        $company = function_exists('getOption') ? getOption('company_name', config('app.name')) : config('app.name');

        try {
            $message = $company.' invoice #'.$booking->id.'%0A'
                .'Amount: '.$currency.' '.$amount.'%0A'
                .'Pay here: '.$payUrl;
            if (function_exists('sendSMS')) {
                sendSMS([
                    'mobile' => $booking->customer->mobile,
                    'message' => $message,
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('Payment link SMS failed', ['booking' => $booking->id, 'error' => $e->getMessage()]);
        }

        if (! empty($booking->customer->email) && View::exists('emails.payment-link')) {
            try {
                $data = [
                    'booking' => $booking,
                    'pay_url' => $payUrl,
                    'amount' => $amount,
                    'currency' => $currency,
                    'company' => $company,
                    'customer_name' => $booking->customer->name,
                ];
                Mail::send('emails.payment-link', $data, function ($mail) use ($booking, $company) {
                    $mail->to($booking->customer->email, $booking->customer->name)
                        ->subject($company.' - Complete your payment (Invoice #'.$booking->id.')');
                });
            } catch (\Throwable $e) {
                Log::warning('Payment link email failed', ['booking' => $booking->id, 'error' => $e->getMessage()]);
            }
        }
    }
}
