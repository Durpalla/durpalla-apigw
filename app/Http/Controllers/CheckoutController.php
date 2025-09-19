<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use App\Constants\AppConst;
use Illuminate\Http\Request;
use App\Gateways\Bkash;
use App\Gateways\Nagad;
use App\Gateways\Sslcom;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\PaymentLog;
use App\Services\BookingService;
use App\Services\CheckoutService;
use App\Services\PaymentService;
use DGvai\Nagad\Facades\Nagad as NagadPayment;

class CheckoutController extends Controller
{
    private $checkout;
    private $payment;
    private $booking;

    public function __construct(
        CheckoutService $checkout,
        PaymentService  $paymentService,
        BookingService  $bookingService
    )
    {
        $this->checkout = $checkout;
        $this->payment = $paymentService;
        $this->booking = $bookingService;
    }

    public function index()
    {
        return view('checkout.fail');
    }

    public function paynow($orderID)
    {
        if ((int)$orderID <= 0) {
            return redirect(route('checkout.fail'));
        }
        $order = $this->checkout->getOrder((int)$orderID);
        if (!$order <= 0 && $order->status !== AppConst::BOOKING_PENDING) {
            return redirect(route('checkout.fail'));
        }
        return view('checkout.create', compact('order'));
    }

    public function token(Request $request)
    {
        $data = ['status' => false, 'message' => ''];
        $order = Booking::findOrFail($request->order);
        if ($order && $order->status === AppConst::BOOKING_PENDING) {
            if ($request->input('payment_method') !== null) {
                switch ($request->input('payment_method')) {
                    case 'Bkash':
                        $paymentMethod = new Bkash();
                        break;
                    case 'Nagad' :
                        $paymentMethod = new Nagad();
                        break;
                }
                return $this->payment->token($paymentMethod, $order);
            } else {
                $paymentMethod = new Sslcom();
                return $this->payment->token($paymentMethod, $order);
            }
        }
        return response()->json($data);
    }

    public function create(Request $request)
    {
        if ($request->input('payment_method') !== null) {
            switch ($request->input('payment_method')) {
                case 'Bkash':
                    $paymentMethod = new Bkash();
                    break;
                case 'Nagad' :
                    $paymentMethod = new Nagad();
                    break;
            }
            return $this->payment->create($paymentMethod, $request->all());
        } else {
            $paymentMethod = new Sslcom();
            return $this->payment->create($paymentMethod, $request->all());
        }
    }

    public function execute(Request $request)
    {
        if ($request->input('payment_method') !== null) {
            switch ($request->input('payment_method')) {
                case 'Bkash':
                    $paymentMethod = new Bkash();
                    break;
                case 'Nagad' :
                    $paymentMethod = new Nagad();
                    break;
            }
            return $this->payment->execute($paymentMethod, $request->all());
        } else {
            $paymentMethod = new Sslcom();
            return $this->payment->execute($paymentMethod, $request->all());
        }
    }


    public function fail()
    {
        return view('checkout.fail');
    }

    public function success($transactionId)
    {
        $transaction = Payment::where('transaction_id', $transactionId)->first();
        $url = config('paths.frontend_site_url') . '/trips';
        if ($transaction !== null) {
            $url = config('paths.frontend_site_url') . '/trip-details/' . $transaction->booking_id . '?payment=' . $transaction->status;
        }
        return view('checkout.success', compact('url'));
    }

    public function nagadCheckout(Request $request, $bookingId): RedirectResponse
    {
        try {
            $order = Booking::find($bookingId);
//            dd($order->payment);
            return NagadPayment::setOrderID($order->payment->transaction_id)
                ->setAmount($order->total_payable)
                ->checkout()
                ->redirect();
        } catch (\Exception $exception) {
            Log::error($exception);
            return redirect()->route('checkout.fail');
//            dd($exception);
        }
    }

    public function nagadCallback(Request $request): RedirectResponse
    {
        try {
            $verified = NagadPayment::callback($request)->verify();
            $payment = Payment::with('booking.bookingItems')->where('transaction_id', '=', $request->order_id)->first();
            if ($payment === null) {
                throw new \Exception('Transaction not found', 404);
            }
            PaymentLog::create([
                'type' => AppConst::GATEWAY_TYPE_CALLBACK,
                'booking_id' => $payment->booking_id,
                'payment_id' => $payment->id,
                'transaction_id' => $payment->transaction_id,
                'payment_method' => AppConst::PAYMENT_METHOD_NAGAD,
                'data' => $request->all()
            ]);
            if ($verified->success()) {
                $response = $verified->getVerifiedResponse();
                $payment->update([
                    'payment_method' => AppConst::PAYMENT_METHOD_NAGAD,
                    'payment_gateway' => AppConst::PAYMENT_METHOD_NAGAD,
                    'gateway' => strtolower(AppConst::PAYMENT_METHOD_NAGAD),
                    'bank_tran_id' => $response['issuerPaymentRefNo'],
                    'paid_amount' => $response['amount'],
                    'store_amount' => $response['amount'],
                    'status' => AppConst::PAYMENT_SUCCESS
                ]);
                $payment->booking->update(['status' => AppConst::BOOKING_COMPLETE]);
                $payment->booking->bookingItems->each(function ($item, $key) {
                    $item->update(['status' => AppConst::BOOKING_ITEM_ACTIVE]);
                });
                PaymentLog::create([
                    'type' => AppConst::GATEWAY_TYPE_VERIFY,
                    'booking_id' => $payment->booking_id,
                    'payment_id' => $payment->id,
                    'transaction_id' => $payment->transaction_id,
                    'payment_method' => AppConst::PAYMENT_METHOD_NAGAD,
                    'bank_transaction_id' => $response['issuerPaymentRefNo'],
                    'data' => $response
                ]);

                return redirect()->away(config('paths.frontend_site_url') . '/trip-details/' . $payment->booking_id . '?payment=success');
            } else {
                return redirect()->route('checkout.fail', ['order_id' => $request->order_id]);
            }
        } catch (\Exception $exception) {
            Log::error($exception);
            return redirect()->route('checkout.fail');
        }
    }
}
