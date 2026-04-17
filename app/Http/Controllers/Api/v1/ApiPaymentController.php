<?php

namespace App\Http\Controllers\Api\v1;

use App\Helpers\CommonHelper;
use App\Helpers\LogHelper;
use App\Http\Requests\PaymentCreateRequest;
use App\Models\Booking;
use App\Models\BookingItem;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use App\Library\SslCommerz\SslCommerzNotification;
use App\Models\Payment;
use App\Models\Gateway;
use App\Services\PendingBookingPaymentWindow;

class ApiPaymentController extends Controller
{
    private $success = 200;

    public function make(PaymentCreateRequest $request)
    {
        $data = ['success' => false, 'message' => __('Your payment cannot be processed'), 'data' => []];

        try {
            $order = Booking::with(['payment', 'bookingItems', 'customer'])->findOrFail($request->order_id);

            $block = PendingBookingPaymentWindow::reasonPaymentBlocked($order);
            if ($block !== null) {
                $data['success'] = false;
                $data['message'] = $block;

                return response()->json($data, $this->success);
            }

            $payment = $order->payment;
            if (! $payment || ! ($payment->transaction_id ?? null)) {
                $payment = Payment::firstOrNew(['booking_id' => $order->id]);
                if (! $payment->id) {
                    $payment->booking_id = $order->id;
                    $payment->transaction_id = uniqid($order->id . '_', false);
                }
            }

            $payment->gateway_id = $request->input('gateway_id');

            $payable = (float) ($order->total_payable ?? 0);
            if (($payment->paid_amount === null || (float) $payment->paid_amount <= 0) && $payable > 0) {
                $payment->paid_amount = $payable;
                if ($payment->dues === null) {
                    $payment->dues = 0;
                }
            }

            if ((float) ($payment->paid_amount ?? 0) <= 0) {
                $data['message'] = __('Invalid payment amount for this booking.');

                return response()->json($data, $this->success);
            }

            $payment->save();
            $data['data']['id'] = $payment->id;
            $data['data']['booking_id'] = $payment->booking_id;
            $data['data']['transaction_id'] = $payment->transaction_id;
            $gateway = Gateway::with(['credentials', 'params', 'endpoints'])
                ->find($request->input('gateway_id'));
            if (! $gateway) {
                $data['message'] = __('Invalid payment gateway.');

                return response()->json($data, $this->success);
            }

            $gwt = CommonHelper::purseGateway($gateway);

            $gwt->create($payment, $request, $data);
        } catch (\Throwable $exception) {
            Log::error('payment.make failed', [
                'order_id' => $request->input('order_id'),
                'gateway_id' => $request->input('gateway_id'),
                'exception' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ]);
            $data['message'] = config('app.debug')
                ? $exception->getMessage()
                : 'Internal Error. Please try again later.';
        }

        return response()->json($data);
    }

    public function validateOrder(Request $request)
    {
        $data = ['success' => false, 'message' => __('Your payment cannot be validate')];

        //validation rules
        $validator = Validator::make($request->all(), [
            'tran_id' => 'bail|required|exists:payments,transaction_id',
            'amount' => 'bail|required',
            'currency' => 'bail|required',
            'store_amount' => 'required',
            'val_id' => 'bail|required'
        ]);

        //validation fails
        if ($validator->fails()) {
            $data['message'] = $validator->errors()->first();
        } else {
            $transaction = Payment::with(['booking'])->where('transaction_id', $request->tran_id)->first();

            if ($transaction) {

                if ($transaction->status == 'pending') {
                    $sslc = new SslCommerzNotification();
                    $validation = $sslc->orderValidate($request->tran_id, $request->amount, $request->currency, $request->all());

                    if ($validation == TRUE) {
                        $transaction->paid_amount = floatval($request->input('amount'));
                        $transaction->bank_tran_id = $request->bank_tran_id;
                        $transaction->payment_method = $request->card_type;
                        $transaction->account_no = $request->card_no;
                        $transaction->store_amount = $request->store_amount;
                        $transaction->currency = $request->currency;
                        $transaction->status = 'success';

                        if ($transaction->save()) {

                            Booking::where('id', $transaction->booking_id)->update([
                                'status' => 'COMPLETE'
                            ]);

                            BookingItem::where('booking_id', $transaction->booking_id)->update([
                                'status' => 1
                            ]);

                            $order = Booking::find($transaction->booking_id);
                            $message = 'Ticket-' . $order->id . '%0A';
                            $scheduleSms = [];
                            if ($order->bookingItems) {
                                foreach ($order->BookingItems as $item) {
                                    $scheduleSms[$item->trip_id][] = $item;
                                }
                            }
                            if ($scheduleSms) {
                                foreach ($scheduleSms as $key => $items) {
                                    $message .= $items[0]->launch['name'] . '<>' . date('d-m-Y h:iA', strtotime($items[0]->trip['leaving_at'])) . '<>' . $items[0]->customer['mobile'];
                                    foreach ($items as $k => $item) {
//                                if ($k > 0) {
//                                    $message .= ',';
//                                }
                                        $passenger = json_decode($item->passenger);
                                        if ($item->booking_type != 'deck') {
                                            $message .= '<>' . $item->item['cabinType']['name'] . ' ' . $item->item['type'] . ' (' . $item->item['cabinType']['letter'] . '-' . $item->item['cabin_no'] . ')';
                                        } else {
                                            $message .= '<>Deck(' . $passenger->person . ')';
                                        }
                                    }
                                }
                            }
                            $message .= '%0ASafe travels!';
                            sendSMS([
                                'mobile' => $order->customer->mobile,
                                'message' => $message
                            ]);

                            $data['code'] = 700;
                            $data['success'] = true;
                            $data['message'] = __('Your payment has been successfully verified');
                        }
                    } else {
                        $transaction->status = 'fail';
                        $transaction->save();
                        Booking::where('id', $transaction->booking_id)->update([
                            'status' => 'FAILED'
                        ]);
                        BookingItem::where('booking_id', $transaction->booking_id)->update([
                            'status' => 2
                        ]);
                        $data['code'] = 703;
                        $data['message'] = 'Payment failed';
                    }
                } elseif ($transaction->status == 'success') {
                    $data['code'] = 701;
                    $data['success'] = true;
                }
            } else {
                $data['code'] = 703;
            }
        }

        return response()->json($data, $this->success);
    }

    public function verify(Request $request)
    {
        $data = ['success' => false, 'message' => __('Your payment cannot be validate')];

        try {
            $payment = Payment::with(['booking'])->where('booking_id', $request->input('booking_id'))->first();

            if ($payment) {
                $gwt = CommonHelper::purseGateway($payment->gateway);
                $data = ['uuid' => $payment->uuid];
                $gwt->verify($payment, $request, $data);

                $payment->refresh();
                $data['success'] = true;
                $data['message'] = __('Your payment has been verified');
                $data['data'] = $payment->format();
                $data['data']['booking'] = $payment->booking->format();
            }
        } catch (\Exception $exception) {
            LogHelper::error($exception->getMessage(), [
                'keyword' => 'PAYMENT_VERIFY_EXCEPTION',
                'request-data' => $request->all(),
            ]);
        }

        return response()->json($data);
    }
}
