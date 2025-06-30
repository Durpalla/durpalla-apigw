<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Library\SslCommerz\SslCommerzNotification;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\Payment;
use App\Models\User;
use PDF;


class SslCommerzPaymentController3 extends Controller
{

    public function index( Request $request )
    {
        if( $request->input('transaction_id') && $request->input('order_id') ) {
            $order = Booking::with(['payment', 'bookingItems', 'customer'])->find( $request->order_id );

            if( $order ) {

                $sslcom = [
                    'total_amount' => round(($order->total_payable), 2),
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

                if( $request->payment_method ) {
//                    $sslcom['multi_card_name'] = $request->payment_method;
                }

                if( !$order->payment || !$order->payment['transaction_id'] ) {
                    $payment = Payment::firstOrNew(['booking_id' => $order->id]);
                    $payment->transaction_id = ( $request->transaction_id ) ? $request->transaction_id : uniqid($order->id . '_', true);

                    if( $payment->save() ) {
                        $sslcom['tran_id'] = $payment->transaction_id;
                    }
                } else {
                    $sslcom['tran_id'] = $order->payment['transaction_id'];
                }
                // dd( $sslcom );
                $sslc = new SslCommerzNotification();
                # initiate(Transaction Data , false: Redirect to SSLCOMMERZ gateway/ true: Show all the Payement gateway here )
                $payment_options = $sslc->makePayment($sslcom, 'hosted');
                if (!is_array($payment_options)) {
                    print_r($payment_options);
                    $payment_options = array();
                }
            }
        }
    }

    public function paynow( Request $request, $id )
    {
        $id = (int) $id;
        if( $id ) {
            $order = Booking::with(['payment', 'bookingItems', 'customer'])->find( $id );
            if( $order ) {
                $sslcom = [
                    'total_amount' => round(($order->total_payable), 2),
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
                    if( !$payment->transaction_id) {
                        $payment->transaction_id = uniqid($order->id . '_', true);
                    }
                    if( $payment->save() ) {
                        $sslcom['tran_id'] = $payment->transaction_id;
                    }
                } else {
                    $sslcom['tran_id'] = $order->payment['transaction_id'];
                }
                // dd( $sslcom );
                $sslc = new SslCommerzNotification();
                # initiate(Transaction Data , false: Redirect to SSLCOMMERZ gateway/ true: Show all the Payement gateway here )
                $payment_options = $sslc->makePayment($sslcom, 'hosted');
                if (!is_array($payment_options)) {
                    print_r($payment_options);
                    $payment_options = array();
                }
            }
        }
    }
    public function paymentSuccess( Request $request )
    {
        $transaction = Payment::with(['booking'])->where('transaction_id', $request->tran_id)->first();

        if( $transaction ) {

            if( $transaction->status == 'pending' ) {
                $sslc = new SslCommerzNotification();
                $validation = $sslc->orderValidate($request->tran_id, $request->amount, $request->currency, $request->all());

                if ($validation == TRUE) {
                    DB::beginTransaction();
                    try{
                        $transaction->paid_amount = round( $request->input('amount'),2);
                        $transaction->bank_tran_id = $request->bank_tran_id;
                        $transaction->payment_method = $request->card_type;
                        $transaction->account_no = $request->card_no;
                        $transaction->store_amount = $request->store_amount;
                        $transaction->currency = $request->currency;
                        $transaction->status = 'success';

                        if( $transaction->save() ) {
                            BookingItem::where(['booking_id' => $transaction->booking_id])->update(['status' => 1]);

                            Booking::where(['id' => $transaction->booking_id])->update([
                                'status' => 'COMPLETE'
                            ]);

                            \LogActivity::addToLog('Payment confirmed through SSLCommerz for booking ID: ' . $transaction->booking_id);

                            DB::commit();

                            //sending email to customer with attachment
                            $booking = Booking::with(['bookingItems.trip.route', 'cancellations', 'bookingItems.item.cabinType', 'bookingItems.trip.launch', 'payment', 'customer'])->findOrFail($transaction->booking_id);
                            // Send data to the view using loadView function of PDF facade
                            // $pdf = PDF::loadView('emails.invoice', $customers);
                            $responseArr = [];
                            if( $booking ) {
                                $responseArr['id'] = $booking->id;
                                $responseArr['pnr'] = $booking->id;
                                $responseArr['qr'] = asset('qrs/' . $booking->id . '.png');
                                $responseArr['booking_date'] = date('Y-m-d H:i:s', strtotime( $booking->created_at ) );
                                $responseArr['booking_date_formated'] = date('d M, Y h:i A', strtotime( $booking->created_at ) );
                                $responseArr['payment_status'] = $booking->payment['status'];
                                $responseArr['total_amount'] = $booking->total_amount;
                                $responseArr['total_discount'] = $booking->total_discount;
                                $responseArr['vat_amount'] = $booking->vat_amount;
                                $responseArr['vat_total'] = $booking->vat_total;
                                $responseArr['charge_amount'] = $booking->charge_amount;
                                $responseArr['charge_total'] = $booking->charge_total;
                                $responseArr['total_payable'] = number_format(($booking->total_amount + $booking->vat_total + $booking->charge_total - $booking->total_discount),2);
                                $responseArr['payment'] = $booking->payment;
                                $responseArr['customer'] = $booking->customer;
                                $responseArr['items'] = [];

                                $cancellations = [];
                                if( $booking->cancellations ) {
                                    foreach( $booking->cancellations as $cancellation ) {
                                        $cancellations = array_merge_recursive( $cancellations, explode(',', $cancellation->items) );
                                    }
                                }

                                // $responseArr['status'] = $booking->status;

                                foreach( $booking->bookingItems as $item ) {
                                    $row = [
                                        'id' => $item['id'],
                                        'cabin_no' => ( $item['item'] ) ? $item['item']['cabinType']['letter'] . '-' . $item['item']['cabin_no'] : '',
                                        'cabin_type' => $item['booking_type'],
                                        'price' => $item['price'],
                                        'cabin_position' => $item['cabin_position'],
                                        'discount' => $item['discount'],
                                        'is_ac' => ($item['item']) ? $item['item']['cabinType']['is_ac'] : null,
                                        'launch_name' => $item['trip']['launch']['name'],
                                        'route_name' => $item['trip']['route']['route_name'],
                                        'schedule_date' => date('d F Y', strtotime( $item['trip_date'] ) ),
                                        'leaving_time' => $item['trip']['leaving_at'],
                                        'leaving_time_formated' => date('h:i A', strtotime($item['trip']['leaving_at'])),
                                        'boarding_point' => json_decode($item['boarding_point']),
                                        'passenger' => json_decode($item['passenger']),
                                        'from' => ($item['trip']['schedule_type'] == 'reverse') ? $item['trip']['endingPoint']['ghat']['name'] : $item['trip']['startingPoint']['ghat']['name'],
                                        'to' => ($item['trip']['schedule_type'] == 'reverse') ? $item['trip']['startingPoint']['ghat']['name'] : $item['trip']['endingPoint']['ghat']['name'],
                                        'cancellable' => ($item['trip_date'] >= date('Y-m-d')) ? (( in_array($item['id'], $cancellations) ) ? false : true) : false,
                                        'status' => $item['status']
                                    ];
                                    if($item['booking_type'] == 'deck' && $item['deck']) {
                                        $row['from'] = ($item['trip']['schedule_type'] == 'reverse' ) ? $item['deck']['departureTo']['ghat']['name'] : $item['deck']['departureFrom']['ghat']['name'];
                                        $row['to'] = ($item['trip']['schedule_type'] == 'reverse' ) ? $item['deck']['departureFrom']['ghat']['name'] : $item['deck']['departureTo']['ghat']['name'];
                                    }
                                    if( $item['trip']['schedule_type'] == 'reverse' ) {
                                        $row['route_name'] = $item['trip']['endingPoint']['ghat']['name'] . ' - ' . $item['trip']['startingPoint']['ghat']['name'];
                                    } else {
                                        $row['route_name'] = $item['trip']['startingPoint']['ghat']['name'] . ' - ' . $item['trip']['endingPoint']['ghat']['name'];
                                    }
                                    array_push($responseArr['items'], $row);
                                }

                                $responseArr['items'] = ( $responseArr['items'] ) ? _my_group_by($responseArr['items'], 'schedule_date' ) : [];

                                $tickets = [];
                                foreach( $responseArr['items'] as $key => $items ) {
                                    array_push($tickets, ['date' => $key, 'tickets' => $items]);
                                }

                                $responseArr['items'] = $tickets;
                            }
                            $pdf = PDF::loadView('emails.invoice',['invoice' => $responseArr]);
                            $user = User::find($transaction->customer_id);
                            if( $user->email ) {
                                try {
                                    \Mail::send('emails.booking', $user->toArray(), function ($message) use ($user, $pdf) {
                                        $message->to($user->email, $user->name)
                                            ->subject('Launch ticket purchase')
                                            ->attachData($pdf->output(), "invoice.pdf");
                                    });
                                    $order = Booking::find($booking->id);
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
                                } catch (\Exception $e) {
                                }
                            }
                            return redirect()->route('checkout.success');
                        }
                    } catch( \Exception $e ) {
                        DB::rollback();
                    }
                } else {
                    $transaction->status = 'failed';
                    $transaction->save();
                }
            } elseif( $transaction->status == 'success' ) {
                return redirect()->route('checkout.success');
            }
        }
        return redirect()->route('checkout.failed');
    }

    public function fail(Request $request)
    {
        if( $request->tran_id ) {
            $transaction = Payment::with(['booking'])->where('transaction_id', $request->tran_id)->first();

            if ($transaction) {
                $transaction->status = 'failed';
                $transaction->save();

                Booking::where(['id' => $transaction->booking_id])->update([
                    'status' => 'FAILED'
                ]);
                BookingItem::where(['booking_id' => $transaction->booking_id])->update(['status' => 2]);
                return redirect()->route('checkout.failed');
            }
        } else {
            die($request->error);
        }
    }

    public function cancel(Request $request)
    {
        $transaction = Payment::with(['booking'])->where('transaction_id', $request->tran_id)->first();

        if( $transaction ) {
            $transaction->status = 'canceled';
            $transaction->save();

            Booking::where(['id' => $transaction->booking_id])->update([
                'status' => 'FAILED'
            ]);
            BookingItem::where(['booking_id' => $transaction->booking_id])->update(['status' => 2]);
            return redirect()->route('checkout.failed');
        }
    }

    public function ipn(Request $request)
    {
        $transaction = Payment::with(['booking'])->where('transaction_id', $request->tran_id)->first();

        if( $transaction ) {

            if( $transaction->status == 'pending' ) {
                $sslc = new SslCommerzNotification();
                $validation = $sslc->orderValidate($request->tran_id, $request->amount, $request->currency, $request->all());

                if ($validation == TRUE) {
                    DB::beginTransaction();
                    try{
                        $transaction->paid_amount = round( $request->input('amount'), 2);
                        $transaction->transaction_id = $request->bank_tran_id;
                        $transaction->payment_method = $request->card_type;
                        $transaction->account_no = $request->card_no;
                        $transaction->store_amount = $request->store_amount;
                        $transaction->currency = $request->currency;
                        $transaction->status = 'success';

                        if( $transaction->save() ) {
                            BookingItem::where(['booking_id' => $transation->booking_id])->update(['status' => 1]);

                            Booking::where(['id' => $transaction->booking_id])->update([
                                'status' => 'ACTIVE'
                            ]);
                            DB::commit();
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
                            return true;
                        }
                    } catch( \Exception $e ) {
                        DB::rollback();
                        return false;
                    }
                } else {
                    $transaction->status = 'failed';
                    $transaction->save();
                    Booking::where(['id' => $transaction->booking_id])->update([
                        'status' => 'FAILED'
                    ]);
                    DB::commit();
                    return false;
                }
            } elseif( $transaction->status == 'success' ) {
                return true;
            }
        } else {
            return false;
        }
    }

}
