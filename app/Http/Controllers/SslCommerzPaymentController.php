<?php
namespace App\Http\Controllers;

use App\Constants\AppConst;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use App\Models\Booking;
use App\Models\BookingItem;
use App\Jobs\SendBookingInvoiceJob;
use App\Library\Sslcommerz\SslCommerzNotification;
use Illuminate\Support\Facades\DB;
use App\Models\Payment;
use App\Models\ScheduleCabinMapping;
use App\Services\BookingService;
use App\Services\CalculationService;
use Modules\Booking\Events\BookingFailedEvent;
use Modules\Booking\Jobs\BookingCommissionCalculationJob;
use Modules\Payment\Events\PaymentCompleteEvent;
use PDF;
use Rajtika\SSLCommerz\Services\SSLCommerz;


class SslCommerzPaymentController extends Controller
{

    public $booking;
    public $calculation;

    public function __construct(BookingService $bookingService, CalculationService $calculationService) {
        $this->booking = $bookingService;
        $this->calculation = $calculationService;
    }

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
    public function paymentSuccess( Request $request ): RedirectResponse
    {
        $transaction = Payment::with(['booking.bookingItems'])->where('transaction_id', $request->tran_id)->first();

        if( $transaction ) {
            if( $transaction->booking->status == AppConst::BOOKING_PENDING ) {
                $sslc = new SslCommerzNotification();
                $validation = $sslc->orderValidate($request->tran_id, $request->amount, $request->currency, $request->all());

                if ($validation == TRUE) {
                    try{
                        DB::transaction(function() use($transaction, $request) {
                            $transaction->update([
                                'paid_amount' => round($request->input('amount'), 2),
                                'bank_tran_id' => $request->bank_tran_id,
                                'payment_method' => $request->card_type,
                                'account_no' => $request->card_no,
                                'store_amount' => $request->store_amount,
                                'currency' => $request->currency,
                                'status' => AppConst::PAYMENT_SUCCESS,
                            ]);
                            $transaction->booking->update([
                                'status' => AppConst::BOOKING_COMPLETE
                            ]);
                            $transaction->booking->bookingItems->each(function ($item, $key) {
                                $item->update(['status' => AppConst::BOOKING_ITEM_ACTIVE]);
                            });
                            event(new PaymentCompleteEvent($transaction->booking));
                        });
                        return redirect()->route('checkout.success');
                    } catch( \Exception $e ) {
                        session()->flash('error', $e->getMessage());
                    }
                } else {
                    $transaction->status = 'failed';
                    $transaction->save();
                    event(new BookingFailedEvent($transaction->booking));
                }
            } elseif( $transaction->status == 'success' ) {
                return redirect()->route('checkout.success');
            }
        }

        return redirect()->route('checkout.fail');
    }

    public function fail(Request $request): RedirectResponse
    {
        if( $request->tran_id ) {
            $transaction = Payment::with(['booking.bookingItems'])->where('transaction_id', $request->tran_id)->first();

            if ($transaction) {
                $transaction->status = 'failed';
                $transaction->save();

                event(new BookingFailedEvent($transaction->booking));
            }
        }
        return redirect()->route('checkout.fail');
    }

    public function cancel(Request $request): RedirectResponse
    {
        $transaction = Payment::with(['booking.bookingItems'])->where('transaction_id', $request->tran_id)->first();

        if( $transaction ) {
            $transaction->status = AppConst::PAYMENT_CANCELLED;
            $transaction->save();

            event(new BookingFailedEvent($transaction->booking));
       }
        return redirect()->route('checkout.fail');
    }

    public function ipn(Request $request)
    {
        $transaction = Payment::with(['booking.bookingItems'])->where('transaction_id', $request->tran_id)->first();

        if( $transaction ) {
            if( $transaction->status == 'pending' ) {
                $sslc = new SslCommerzNotification();
                $validation = $sslc->orderValidate($request->tran_id, $request->amount, $request->currency, $request->all());

                if ($validation == TRUE) {
                    try{
                        DB::transaction(function() use($transaction, $request) {
                            $transaction->update([
                                'paid_amount' => round($request->input('amount'), 2),
                                'bank_tran_id' => $request->bank_tran_id,
                                'payment_method' => $request->card_type,
                                'account_no' => $request->card_no,
                                'store_amount' => $request->store_amount,
                                'currency' => $request->currency,
                                'status' => AppConst::PAYMENT_SUCCESS,
                            ]);
                            $transaction->booking->update([
                                'status' => AppConst::BOOKING_COMPLETE
                            ]);
                            $transaction->booking->bookingItems->each(function ($item, $key) {
                                $item->update(['status' => AppConst::BOOKING_ITEM_ACTIVE]);
                            });
                            event(new PaymentCompleteEvent($transaction->booking));
                        });
                        return true;
                    } catch( \Exception $e ) {
                        return false;
                    }
                } else {
                    $transaction->status = 'failed';
                    $transaction->save();
                    $transaction->booking->update([
                        'status' => 'FAILED'
                    ]);
                    event(new BookingFailedEvent($transaction->booking));
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
