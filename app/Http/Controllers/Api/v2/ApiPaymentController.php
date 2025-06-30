<?php
namespace App\Http\Controllers\Api\v2;

use App\Models\Booking;
use App\Models\BookingItem;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Library\SslCommerz\SslCommerzNotification;
use App\Models\Payment;

class ApiPaymentController extends Controller
{
	private $success = 200;

    public function make( Request $request )
    {
    	$data = ['status' => 'FAILED', 'message' => __('Your payment cannot be processed')];

        //validation rules
        $validator = Validator::make($request->all(), [
            'order' => 'bail|required|integer|exists:bookings,id'
        ]);

        //validation fails
        if ( $validator->fails() ) {
            $data['message'] = $validator->errors()->first();
        } else {
        	$order = Booking::with(['payment', 'bookingItems', 'customer'])->findOrFail( $request->order );

        	$sslcom = [
        		'total_amount' => $order->total_amount,
        		'currency' => 'BDT',
        		'cus_name' => $order->customer['name'],
        		'cus_email' => $order->customer['email'],
        		'cus_phone' => $order->customer['mobile'],
                'cus_add1' => "Dhaka",
                'cus_add2' => "",
                'cus_city' => "",
                'cus_state' => "",
                'cus_country' => "Country",
                'cus_fax' => "",
                'cus_postcode' => "",
                'ship_name' => "",
                'ship_add1' => "",
                'ship_add2' => "",
                'ship_city' => "",
                'ship_state' => "",
                'ship_country' => "",
                'ship_postcode' => "",
                'ship_phone' => "",
        		'shipping_method' => 'NO',
        		'product_name' => 'Launch Ticket',
        		'product_category' => 'Ticket',
        		'product_profile' => 'general'
        	];

        	if( !$order->payment || !$order->payment['transaction_id'] ) {
        		$payment = Payment::firstOrNew(['booking_id' => $order->id]);
                if( !$payment->id ) {
                    $payment->booking_id = $order->id;
                    $payment->transaction_id = uniqid($order->id . '_', false);
                }

        		if( $payment->save() ) {
        			$sslcom['tran_id'] = $payment->transaction_id;
        		}
        	} else {
        		$sslcom['tran_id'] = $order->payment['transaction_id'];
        	}

        	$sslc = new SslCommerzNotification();
        	$sslResponse = $sslc->makePayment($sslcom, 'checkout', 'json');

			if (!is_array($sslResponse)) {
			    print_r($sslResponse);
			    $sslResponse = array();
			}
        }
    }

    public function validateOrder( Request $request )
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
        if ( $validator->fails() ) {
            $data['message'] = $validator->errors()->first();
        } else {
            $transaction = Payment::with(['booking'])->where('transaction_id', $request->tran_id)->first();

            if( $transaction ) {

                if( $transaction->status == 'pending' ) {
                    $sslc = new SslCommerzNotification();
                    $validation = $sslc->orderValidate($request->tran_id, $request->amount, $request->currency, $request->all());

                    if ($validation == TRUE) {
                        $transaction->paid_amount = floatval( $request->input('amount'));
                        $transaction->bank_tran_id = $request->bank_tran_id;
                        $transaction->payment_method = $request->card_type;
                        $transaction->account_no = $request->card_no;
                        $transaction->store_amount = $request->store_amount;
                        $transaction->currency = $request->currency;
                        $transaction->status = 'success';

                        if( $transaction->save() ) {

                            Booking::where('id', $transaction->booking_id)->update([
                                'status' => 'COMPLETE'
                            ]);

                            BookingItem::where('booking_id', $transaction->booking_id)->update([
                                'status' => 1
                            ]);

                            $order = Booking::find($transaction->booking_id);
                            $message = 'Ticket-' . $order->id . '%0A';
                            $scheduleSms = [];
                            if( $order->bookingItems ) {
                                foreach ($order->BookingItems as $item) {
                                    $scheduleSms[$item->trip_id][] = $item;
                                }
                            }
                            if( $scheduleSms ) {
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
                } elseif( $transaction->status == 'success' ) {
                    $data['code'] = 701;
                    $data['success'] = true;
                }
            } else {
                $data['code'] = 703;
            }
        }

        return response()->json($data, $this->success);
    }
}
