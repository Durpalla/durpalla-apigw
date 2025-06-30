<?php

namespace App\Http\Controllers\Api\v1;

use App\Helpers\LogActivity;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\CabinLock;
use App\Models\Coupon;
use App\Models\DeckFare;
use App\Models\Payment;
use App\Models\VehicleSchedule;
use App\Services\BookingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ApiOrderController extends Controller
{
    protected $success = 200;
    private $bookingService;
    public function __construct( BookingService $bookingService)
    {
        $this->bookingService = $bookingService;
    }
    /**
     * Confirm order.
     *
     * @return \Illuminate\Http\Response
     */
    public function confirm( Request $request )
    {
        $data = ['success' => false, 'message' => 'Your order cannot be confirmed'];
        //validation rules
        $validator = Validator::make($request->all(), [
            'items' => 'bail|required|string',
            'coupon' => 'bail|nullable|string',
            'platform' => 'bail|nullable|string',
            'customer_token' => 'bail|string'
        ]);

        //validation fails
        if ( $validator->fails() )
            return response()->json(['success'=> false, 'message' => $validator->errors()->first()], $this->success );

        $items = json_decode( str_replace("\\", "",$request->items) );

        if( is_array( $items ) ) {
            DB::beginTransaction();
            try{
                $validation = $this->bookingService->validateItems($items);
                if($validation['status'] === false) {
                    throw new \Exception($validation['message']);
                }
                $booking_items = [];
                $coupon = Coupon::where(['code' => $request->coupon, 'status' => 1])->first();
                $vat_amount = abs(getOption('vat_amount', 0));
                $charge_amount = ( $request->platform == 'web' ) ? getOption('service_charge_web', 0) : getOption('service_charge_mobile', 0);
                $charge_amount = abs( $charge_amount );

                $booking = Booking::create([
                    'booking_date' => date('Y-m-d'),
                    'customer_id' => Auth::user()->id,
                    'user_id' => Auth::user()->id,
                    'total_amount' => 0,
                    'total_discount' => 0,
                    'vat_amount' => $vat_amount,
                    'charge_amount' => $charge_amount,
                    'total_payable' => 0,
                    'vat_total' => 0,
                    'platform' => ($request->platform == 'web') ? 'web' : 'android'
                ]);

                // DB::rollback();
                // return response()->json($booking);
                foreach( $items as $item ) {
                    $trip = VehicleSchedule::with(['launch.merchant'])->find( $item->trip_id);
                    $item->type = $item->cabin_type;
                    $trip_date = ( $item->trip_date ) ? date('Y-m-d', strtotime( $item->trip_date ) ) : date('Y-m-d');
                    $discount = 0;
                    $route_name = ($trip->schedule_type == 'reverse') ? $trip->endingPoint->ghat['name'] . ' - ' . $trip->startingPoint->ghat['name'] : $trip->startingPoint->ghat['name'] . ' - ' . $trip->endingPoint->ghat['name'];
                    if($item->type == 'deck') {
                        $deck = DeckFare::find($item->cabin_id);
                        if ($deck) {
                            $route_name = ($trip->schedule_type == 'reverse') ? $deck->departureTo->ghat->name . ' - ' . $deck->departureFrom->ghat->name : $deck->departureFrom->ghat->name . ' - ' . $deck->departureTo->ghat->name;
                        }
                    }
                    $item->vat_applicable_to = $trip->launch['merchant']->vat_applicable_to;

                    if( $item->vat_applicable_to == 'customer' ) {
                        $booking->vat_total += abs( $item->cabin_fare*($vat_amount / 100) );
                    }

                    if( $coupon && $coupon->offer_start <= date('Y-m-d') && $coupon->offer_end >= date('Y-m-d')) {
                        $applicablesTo = explode(',', $coupon->items);
                        switch ( $coupon->type ) {
                            case 'customer':
                                if( in_array(Auth::user()->id, $applicablesTo) ) {
                                    if( ($item->cabin_type == 'cabin' && $coupon->is_cabin) || ($item->cabin_type == 'seat' && $coupon->is_seat) || ($item->cabin_type == 'deck' && $coupon->is_deck) ) {
                                        $discount += ( $coupon->discount_type == 'flat' ) ? $coupon->discount_amount : $item->cabin_fare*($coupon->discount_amount / 100);
                                    }
                                }
                            break;

                            case 'merchant':
                                if( in_array($item->merchant_id, $applicablesTo) ) {
                                    if( ($item->cabin_type == 'cabin' && $coupon->is_cabin) || ($item->cabin_type == 'seat' && $coupon->is_seat) || ($item->cabin_type == 'deck' && $coupon->is_deck) ) {
                                        $discount += ( $coupon->discount_type == 'flat' ) ? $coupon->discount_amount : $item->cabin_fare*($coupon->discount_amount / 100);
                                    }
                                }
                            break;

                            case 'route':
                                if( in_array($item->route_id, $applicablesTo) ) {
                                    if( ($item->cabin_type == 'cabin' && $coupon->is_cabin) || ($item->cabin_type == 'seat' && $coupon->is_seat) || ($item->cabin_type == 'deck' && $coupon->is_deck) ) {
                                        $discount += ( $coupon->discount_type == 'flat' ) ? $coupon->discount_amount : $item->cabin_fare*($coupon->discount_amount / 100);
                                    }
                                }
                            break;

                            case 'launch':
                                if( in_array($item->launch_id, $applicablesTo) ) {
                                    if( ($item->cabin_type == 'cabin' && $coupon->is_cabin) || ($item->cabin_type == 'seat' && $coupon->is_seat) || ($item->cabin_type == 'deck' && $coupon->is_deck) ) {
                                        $discount += ( $coupon->discount_type == 'flat' ) ? $coupon->discount_amount : $item->cabin_fare*($coupon->discount_amount / 100);
                                    }
                                }
                            break;

                            case 'period':
                                    if( ($item->cabin_type == 'cabin' && $coupon->is_cabin) || ($item->cabin_type == 'seat' && $coupon->is_seat) || ($item->cabin_type == 'deck' && $coupon->is_deck) ) {
                                        $discount += ( $coupon->discount_type == 'flat' ) ? $coupon->discount_amount : $item->cabin_fare*($coupon->discount_amount / 100);
                                    }
                            break;
                        }
                    }

                    $booking->total_amount = $booking->total_amount + abs($item->cabin_fare);
                    $booking->total_discount += abs($discount);

                    $passenger = $item->passenger;
                    if( $passenger == null ) {
                        $passenger = ['type' => 'self', 'name' => Auth::user()->name, 'mobile' => Auth::user()->mobile, 'person' => 1];
                    } else {
                        if( $passenger->type == 'self' ) {
                            $passenger->name = Auth::user()->name;
                            $passenger->mobile = Auth::user()->mobile;
                        }
                    }

                    array_push( $booking_items, [
                        'booking_id' => $booking->id,
                        'vehicle_id' => $item->launch_id,
                        'customer_id' => $booking->customer_id,
                        'booking_type' => $item->type,
                        'cabin_id' => ( in_array( $item->type, ['cabin', 'seat'] ) ) ? $item->cabin_id : null,
                        'price' => abs($item->cabin_fare),
                        'trip_id' => $item->trip_id,
                        'trip_date' => $trip_date,
                        'booking_date' => $booking->booking_date,
                        'discount' => $discount,
                        'boarding_point' => ( isset( $item->boardingPoint ) ) ? json_encode( $item->boardingPoint ) : null,
                        'passenger' => json_encode( $passenger ),
                        'vat_amount' => $vat_amount,
                        'charge_amount' => $charge_amount,
                        'vat_applicable_to' => $item->vat_applicable_to,
                        'discount_type' => 'coupon',
                        'route_name' => $route_name,
                        'deck_fare_id' => ($item->type == 'deck') ? $item->cabin_id : null
                    ]);

                    if( $item->type != 'deck' ) {
                        CabinLock::where([
                            'cabin_id' => $item->cabin_id,
                            'trip_id' => $item->trip_id
                        ])->delete();
                    }
                }

                // DB::rollback();
                // return ( $booking_items );

                //save items
                BookingItem::insert( $booking_items );

                //update order with total amount
                $booking->total_amount = abs( $booking->total_amount );
                $booking->charge_total = abs( $booking->total_amount*($charge_amount / 100) );
                $booking->total_payable = abs( ( $booking->total_amount + $booking->vat_total + $booking->charge_total - $booking->total_discount) );
                if( $booking->save() ) {

                    //set payment record
                    $payment = Payment::firstOrnew([
                        'booking_id' => $booking->id
                    ]);
                    $payment->booking_id = $booking->id;
                    $payment->transaction_id = uniqid($booking->id . '_', false);
                    $payment->customer_id = $booking->customer_id;

                    $payment->save();
                    LogActivity::addToLog('Created new booking with ID: ' . $booking->id);

                    DB::commit();
                    $qrCode = \QrCode::size(500)
                      ->format('png')
                      // ->color(33, 152, 118)
                      ->size(500)
                      ->merge(public_path('default/logo-icon.png'), .1, true)
                      ->generate((string)$booking->id, public_path('qrs/' . $booking->id . '.png'));

                    $data['success'] = true;
                    $data['order_id'] = $booking->id;
                    $data['trans_id'] = $payment->transaction_id;
                    $data['message'] = 'Your order has been confirmed.';
                }
            }  catch(\Exception $e) {
                $data['message'] = $e->getMessage();
                DB::rollback();
            }
        }

        return response()->json($data, $this->success);
    }

    public function couponValidate( Request $request ) {
        $data = ['success' => false, 'message' => 'Cannot validate coupon'];
        //validation rules
        $validator = Validator::make($request->all(), [
            'items' => 'bail|required|string',
            'coupon' => 'bail|required|string|exists:coupons,code'
        ]);

        //validation fails
        if ( $validator->fails() )
            return response()->json(['success'=> false, 'message' => $validator->errors()->first()], $this->success );

        $items = json_decode( str_replace("//", "",$request->items) );

        if( is_array( $items ) ) {
            $coupon = Coupon::where(['code' => $request->coupon, 'status' => 1])->first();

            //if coupon is valid
            if($coupon && $coupon->offer_start <= date('Y-m-d') && $coupon->offer_end >= date('Y-m-d')){
                $totalDiscount = 0;
                $applicablesTo = explode(',', $coupon->items);
                switch ( $coupon->type ) {
                    case 'customer':
                        foreach( $items as $item ) {
                            if( in_array(Auth::user()->id, $applicablesTo) ) {
                                if( ($item->cabin_type == 'cabin' && $coupon->is_cabin) || ($item->cabin_type == 'seat' && $coupon->is_seat) || ($item->cabin_type == 'deck' && $coupon->is_deck) ) {
                                    $totalDiscount += ( $coupon->discount_type == 'flat' ) ? $coupon->discount_amount : $item->cabin_fare*($coupon->discount_amount / 100);
                                }

                            }
                        }
                        break;

                    case 'merchant':
                        foreach( $items as $item ) {
                            if( in_array($item->merchant_id, $applicablesTo) ) {
                                if( ($item->cabin_type == 'cabin' && $coupon->is_cabin) || ($item->cabin_type == 'seat' && $coupon->is_seat) || ($item->cabin_type == 'deck' && $coupon->is_deck) ) {
                                    $totalDiscount += ( $coupon->discount_type == 'flat' ) ? $coupon->discount_amount : $item->cabin_fare*($coupon->discount_amount / 100);
                                }
                            }
                        }
                        break;

                    case 'route':
                        foreach( $items as $item ) {
                            if( in_array($item->route_id, $applicablesTo) ) {
                                if( ($item->cabin_type == 'cabin' && $coupon->is_cabin) || ($item->cabin_type == 'seat' && $coupon->is_seat) || ($item->cabin_type == 'deck' && $coupon->is_deck) ) {
                                    $totalDiscount += ( $coupon->discount_type == 'flat' ) ? $coupon->discount_amount : $item->cabin_fare*($coupon->discount_amount / 100);
                                }
                            }
                        }
                        break;

                    case 'launch':
                        foreach( $items as $item ) {
                            if( in_array($item->launch_id, $applicablesTo) ) {
                                if( ($item->cabin_type == 'cabin' && $coupon->is_cabin) || ($item->cabin_type == 'seat' && $coupon->is_seat) || ($item->cabin_type == 'deck' && $coupon->is_deck) ) {
                                    $totalDiscount += ( $coupon->discount_type == 'flat' ) ? $coupon->discount_amount : $item->cabin_fare*($coupon->discount_amount / 100);
                                }
                            }
                        }
                    break;

                    case 'period':
                        foreach( $items as $item ) {
                            if( ($item->cabin_type == 'cabin' && $coupon->is_cabin) || ($item->cabin_type == 'seat' && $coupon->is_seat) || ($item->cabin_type == 'deck' && $coupon->is_deck) ) {
                                $totalDiscount += ( $coupon->discount_type == 'flat' ) ? $coupon->discount_amount : $item->cabin_fare*($coupon->discount_amount / 100);
                            }
                        }
                        break;
                }

                $data['success'] = true;
                $data['discount'] = $totalDiscount;
                $data['message'] = ( $totalDiscount ) ? 'You have accuire this offer successfully' : 'Coupon is valid but you are not applicable';
            }
        }

        return response()->json( $data, $this->success );
    }

    /**
     * Payment order.
     *
     * @return \Illuminate\Http\Response
     */
    public function payment( Request $request )
    {
        $data = ['success' => false, 'message' => 'Your transaction cannot be instantiate.'];

        //validation rules
        $validator = Validator::make($request->all(), [
            'order_id' => 'bail|required|integer|exists:bookings,id',
            'payment_method' => 'bail|required|string'
        ]);

        //validation fails
        if ( $validator->fails() ) {
            $data['message'] = $validator->errors()->first();
        } else {
            $order = Booking::with(['payment'])->findOrFail( $request->order_id );
            if( !is_null( $order->payment ) ) {
                $payment = Payment::firstOrNew(['booking_id' => $order->id]);
                $payment->booking_id = $order->id;
                $payment->transaction_id = uniqid($order->id . '_', false);

                if( $payment->save() ) {
                    $data['tran_id'] = $payment->transaction_id;
                    $data['success'] = true;
                    $data['message'] = 'Your payment has been successfully processed';
                }
            } else {
                $data['success'] = true;
                $data['message'] = 'Your payment has been successfully processed';
                $data['tran_id'] = $order->payment->transaction_id;
            }
        }

        return response()->json($data, $this->success);
    }
}
