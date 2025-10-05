<?php

namespace App\Http\Controllers\Api\v1;

use App\Models\Booking;
use App\Models\BookingCancellation;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\BookingCancellationItem;
use App\Notifications\BookingCancelRequest;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class ApiBookingController extends Controller
{
    protected $success = 200;
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function check( Request $request, $id )
    {
        $data = ['success' => false, 'message' => __('Your PNR is not valid')];
        $booking = Booking::with('cancellations', 'customer', 'bookingItems.item.cabinType', 'bookingItems.trip.vehicle', 'bookingItems.trip.route')
        ->where(['customer_id' => Auth::user()->id, 'id' => $id])->first();

        if( $booking ) {
            $data['item'] = $booking->format();
            $data['success'] = true;
            $data['message'] = 'Booking found';
        }

        return response()->json($data, $this->success);
    }

    public function cancelBooking( Request $request )
    {
        $data = ['success' => false, 'message' => __('Your transaction cannot be instantiate.')];
        //validation rules
        $validator = Validator::make($request->all(), [
            'booking_id' => 'bail|required|integer|exists:bookings,id',
            'type' => 'bail|required|string',
            'items' => 'bail|required|string'
        ]);

        //validation fails
        if ( $validator->fails() ) {
            $data['content'] = $validator->errors()->first();
        } else {
            if( getOption('is_cancellation_enabled') ) {
                DB::beginTransaction();
                try{
                    $user = auth()->user();
                    $booking = Booking::find($request->booking_id);

                    $cancellations = [];
                    if( $booking->cancellations ) {
                        foreach( $booking->cancellations as $cancellation ) {
                            $cancellations = array_merge_recursive( $cancellations, explode(',', $cancellation->items) );
                        }
                    }

                    $items = [];
                    $requestItems = json_decode( $request->items );
                    $inCancellation = false;
                    $cancellationItems = [];
                    $bookingItems = collect($booking->bookingItems);
                    $totalRefundable = 0;
                    if( is_array( $requestItems ) ) {
                        foreach( $requestItems as $item ) {
                            if($cancellations && in_array($item->id, $cancellations)) {
                                $inCancellation = true;
                            }
                            array_push($items, $item->id);
                            $bookingItem = $bookingItems->find($item);
                            $refundableAmount = $this->calculation->calculateRefundableAmount($bookingItem->toArray());
                            $totalRefundable += $refundableAmount;
                            array_push($cancellationItems, [
                                'booking_id' => $booking->id,
                                'booking_item_id' => $bookingItem->id,
                                'customer_id' => $booking->customer_id,
                                'officer_id' => $user->id,
                                'refundable_amount' => $refundableAmount
                            ]);
                        }
                    }

                    if( $inCancellation == false ) {
                        // return $booking;
                        $cancellation = BookingCancellation::create([
                            'booking_id' => $request->booking_id,
                            'type' => $request->type,
                            'customer_id' => $booking->customer_id,
                            'user_id' => Auth::user()->id,
                            'transaction_id' => 1,
                            'vat_refundable' => (int) getOption('is_vat_refundable'),
                            'charge_refundable' => (int) getOption('is_charge_refundable'),
                            'items' => implode(',', $items),
                            'refundable_amount' => $totalRefundable
                        ]);
                        foreach ($cancellationItems as $cancellationItem) {
                            $cancellationItem['booking_cancellation_id'] = $cancellation->id;
                            BookingCancellationItem::create($cancellationItem);
                        }

                        DB::commit();
                        $data['success'] = true;
                        $data['label'] = 'success';
                        $data['message'] = __('Your cancellation request has been sent successfully.');
                        $cancellation->customer->notify(new BookingCancelRequest($cancellation));
                        \LogActivity::addToLog("Request to cancel tickets");
                    } else {
                        throw new \Exception(trans('Sorry! some items already has in cancellations'));
                    }
                } catch( \Exception $e ) {
                    $data['message'] = $e->getMessage();
                    DB::rollback();
                }
            } else {
                $data['message'] = __('Sorry! cancellation is not eligible at this moment');
            }
        }

        return response()->json($data, $this->success);
    }
}
