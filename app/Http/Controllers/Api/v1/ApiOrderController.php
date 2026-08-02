<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Requests\BookingConfirmRequest;
use Illuminate\Http\JsonResponse;
use App\Jobs\BookingQrcodeGenerateJob;
use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\CabinLock;
use App\Models\DeckFare;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use App\Models\VehicleSchedule;
use App\Models\Payment;
use App\Services\ApiIdempotencyService;
use App\Services\BookingService;
use App\Services\Promotion\DTO\PromotionContext;
use App\Services\Promotion\PromotionEngine;

class ApiOrderController extends Controller
{
    protected $success = 200;
    private $bookingService;

    public function __construct(BookingService $bookingService)
    {
        $this->bookingService = $bookingService;
    }

    public function confirm(BookingConfirmRequest $request): JsonResponse
    {
        $idempotency = app(ApiIdempotencyService::class);
        $idemKey = $idempotency->keyFromRequest();
        $actorId = (int) (Auth::id() ?? 0);
        if ($idemKey !== '' && ! $idempotency->isValidKey($idemKey)) {
            return response()->json([
                'success' => false,
                'message' => __('Idempotency-Key must be 1–64 characters.'),
            ], 422);
        }
        if ($idemKey !== '' && $actorId > 0) {
            $cached = $idempotency->find('customer_booking_confirm', $actorId, $idemKey);
            if ($cached) {
                return $cached;
            }
        }

        $data = ['success' => false, 'message' => __('Your booking request is not valid')];
        try {
            $items = $request->input('items');
            if(!is_array($items)) {
                $items = json_decode(str_replace("\\", "", $request->items));
            }
            $itemsTobeValidated = collect($items)->filter(function ($item, $k) {
                $item = (array) $item;
                return $item['type'] != 'deck';
            })->pluck('item_id')->toArray();

            $validation = $this->bookingService->validate($itemsTobeValidated);
            if ($validation['status'] === true) {
                $data = $this->bookingService->confirm($items, $data);
            } else {
                throw new \Exception($validation['message']);
            }
        } catch (\Exception $exception) {
            $data['message'] = $exception->getMessage();
        }

        $response = response()->json($data, $this->success);
        if (! empty($data['success']) && $idemKey !== '' && $actorId > 0) {
            $resourceId = (int) ($data['order_id'] ?? $data['booking_id'] ?? 0) ?: null;
            $idempotency->remember(
                'customer_booking_confirm',
                $actorId,
                $idemKey,
                $data,
                $this->success,
                $resourceId
            );
        }

        return $response;
    }

    public function confirm2(Request $request)
    {
        $data = ['success' => false, 'message' => __('Your order cannot be confirmed')];
        //validation rules
        $validator = Validator::make($request->all(), [
            'items' => 'bail|required|string',
            'coupon' => 'bail|nullable|string',
            'platform' => 'bail|nullable|string',
            'customer_token' => 'bail|string'
        ]);

        //validation fails
        if ($validator->fails())
            return response()->json(['success' => false, 'message' => $validator->errors()->first()], $this->success);

        $items = json_decode(str_replace("\\", "", $request->items));

        if (is_array($items)) {
            DB::beginTransaction();
            try {
                if ($this->bookingService->validateItems($items)) {
                    throw new \Exception(trans('Some items expired or not in your cart.'));
                }
                $booking_items = [];
                $coupon = Coupon::where(['code' => $request->coupon, 'status' => 1])->first();
                $vat_amount = abs(getOption('vat_amount', 0));
                $charge_amount = ($request->platform == 'web') ? getOption('service_charge_web', 0) : getOption('service_charge_mobile', 0);
                $charge_amount = abs($charge_amount);

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

                // DB::rollback();n
                // return response()->json($booking);
                foreach ($items as $item) {
                    $trip = VehicleSchedule::with(['launch.merchant'])->find($item->trip_id);
                    $item->type = $item->cabin_type;
                    $trip_date = ($item->trip_date) ? date('Y-m-d', strtotime($item->trip_date)) : date('Y-m-d');
                    $discount = 0;
                    $route_name = ($trip->schedule_type == 'reverse') ? $trip->endingPoint->ghat['name'] . ' - ' . $trip->startingPoint->ghat['name'] : $trip->startingPoint->ghat['name'] . ' - ' . $trip->endingPoint->ghat['name'];
                    if ($item->type == 'deck') {
                        $deck = DeckFare::find($item->cabin_id);
                        if ($deck) {
                            $route_name = ($trip->schedule_type == 'reverse') ? $deck->departureTo->ghat->name . ' - ' . $deck->departureFrom->ghat->name : $deck->departureFrom->ghat->name . ' - ' . $deck->departureTo->ghat->name;
                        }
                    }
                    $item->vat_applicable_to = $trip->launch['merchant']->vat_applicable_to;

                    if ($item->vat_applicable_to == 'customer') {
                        $booking->vat_total += abs($item->fare * ($vat_amount / 100));
                    }

                    if ($coupon && $coupon->offer_start <= date('Y-m-d') && $coupon->offer_end >= date('Y-m-d')) {
                        $applicablesTo = explode(',', $coupon->items);
                        switch ($coupon->type) {
                            case 'customer':
                                if (in_array(Auth::user()->id, $applicablesTo)) {
                                    if (($item->cabin_type == 'cabin' && $coupon->is_cabin) || ($item->cabin_type == 'seat' && $coupon->is_seat) || ($item->cabin_type == 'deck' && $coupon->is_deck)) {
                                        $discount += ($coupon->discount_type == 'flat') ? $coupon->discount_amount : $item->fare * ($coupon->discount_amount / 100);
                                    }
                                }
                                break;

                            case 'merchant':
                                if (in_array($item->merchant_id, $applicablesTo)) {
                                    if (($item->cabin_type == 'cabin' && $coupon->is_cabin) || ($item->cabin_type == 'seat' && $coupon->is_seat) || ($item->cabin_type == 'deck' && $coupon->is_deck)) {
                                        $discount += ($coupon->discount_type == 'flat') ? $coupon->discount_amount : $item->fare * ($coupon->discount_amount / 100);
                                    }
                                }
                                break;

                            case 'route':
                                if (in_array($item->route_id, $applicablesTo)) {
                                    if (($item->cabin_type == 'cabin' && $coupon->is_cabin) || ($item->cabin_type == 'seat' && $coupon->is_seat) || ($item->cabin_type == 'deck' && $coupon->is_deck)) {
                                        $discount += ($coupon->discount_type == 'flat') ? $coupon->discount_amount : $item->fare * ($coupon->discount_amount / 100);
                                    }
                                }
                                break;

                            case 'launch':
                                if (in_array($item->vehicle_id, $applicablesTo)) {
                                    if (($item->cabin_type == 'cabin' && $coupon->is_cabin) || ($item->cabin_type == 'seat' && $coupon->is_seat) || ($item->cabin_type == 'deck' && $coupon->is_deck)) {
                                        $discount += ($coupon->discount_type == 'flat') ? $coupon->discount_amount : $item->fare * ($coupon->discount_amount / 100);
                                    }
                                }
                                break;

                            case 'period':
                                if (($item->cabin_type == 'cabin' && $coupon->is_cabin) || ($item->cabin_type == 'seat' && $coupon->is_seat) || ($item->cabin_type == 'deck' && $coupon->is_deck)) {
                                    $discount += ($coupon->discount_type == 'flat') ? $coupon->discount_amount : $item->fare * ($coupon->discount_amount / 100);
                                }
                                break;
                        }
                    }

                    $booking->total_amount = $booking->total_amount + abs($item->fare);
                    $booking->total_discount += abs($discount);

                    $passenger = $item->passenger;
                    if ($passenger == null) {
                        $passenger = ['type' => 'self', 'name' => Auth::user()->name, 'mobile' => Auth::user()->mobile, 'person' => 1];
                    } else {
                        if ($passenger->type == 'self') {
                            $passenger->name = Auth::user()->name;
                            $passenger->mobile = Auth::user()->mobile;
                        }
                    }

                    array_push($booking_items, [
                        'booking_id' => $booking->id,
                        'vehicle_id' => $item->vehicle_id,
                        'customer_id' => $booking->customer_id,
                        'booking_type' => $item->type,
                        'cabin_id' => (in_array($item->type, ['cabin', 'seat'])) ? $item->cabin_id : null,
                        'price' => abs($item->fare),
                        'trip_id' => $item->trip_id,
                        'trip_date' => $trip_date,
                        'booking_date' => $booking->booking_date,
                        'discount' => $discount,
                        'boarding_point' => (isset($item->boardingPoint)) ? json_encode($item->boardingPoint) : null,
                        'passenger' => json_encode($passenger),
                        'vat_amount' => $vat_amount,
                        'charge_amount' => $charge_amount,
                        'vat_applicable_to' => $item->vat_applicable_to,
                        'discount_type' => 'coupon',
                        'route_name' => $route_name,
                        'deck_fare_id' => ($item->type == 'deck') ? $item->cabin_id : null
                    ]);

                    if ($item->type != 'deck') {
                        CabinLock::where([
                            'cabin_id' => $item->cabin_id,
                            'trip_id' => $item->trip_id
                        ])->delete();
                    }
                }

                //save items
                BookingItem::insert($booking_items);

                //update order with total amount
                $booking->total_amount = abs($booking->total_amount);
                $booking->charge_total = abs($booking->total_amount * ($charge_amount / 100));
                $booking->total_payable = abs(($booking->total_amount + $booking->vat_total + $booking->charge_total - $booking->total_discount));
                if ($booking->save()) {
                    //set payment record
                    $payment = Payment::firstOrnew([
                        'booking_id' => $booking->id
                    ]);
                    $payment->booking_id = $booking->id;
                    $payment->transaction_id = uniqid($booking->id . '_', false);
                    $payment->customer_id = $booking->customer_id;

                    $payment->save();
                    DB::commit();
                    dispatch(new BookingQrcodeGenerateJob($booking));

                    $data['success'] = true;
                    $data['order_id'] = $booking->id;
                    $data['trans_id'] = $payment->transaction_id;
                    $data['message'] = __('Your order has been confirmed.');
                }
            } catch (\Exception $e) {
                $data['message'] = $e->getMessage() . $e->getLine();
                DB::rollback();
            }
        }

        return response()->json($data, $this->success);
    }

    public function couponValidate(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'items' => 'bail|required',
            'coupon' => 'bail|required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
            ], $this->success);
        }

        $items = $this->parsePromotionItems($request->input('items'));
        if ($items === []) {
            return response()->json([
                'success' => false,
                'message' => __('Cannot validate coupon'),
            ], $this->success);
        }

        $context = PromotionContext::fromArray([
            'user_id' => Auth::id(),
            'service_type' => $request->input('service_type', 'transport'),
            'channel' => $request->input('channel', 'web'),
            'items' => $items,
        ]);

        $result = app(PromotionEngine::class)->applyCode($context, (string) $request->input('coupon'));

        return response()->json([
            'success' => $result->success,
            'message' => $result->message,
            'discount' => $result->discountAmount,
            'promotion_id' => $result->promotion?->id,
            'item_discounts' => $result->itemDiscounts,
        ], $this->success);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function parsePromotionItems(mixed $raw): array
    {
        if (is_string($raw)) {
            $raw = json_decode(str_replace('//', '', $raw), true);
        }

        if (! is_array($raw)) {
            return [];
        }

        $items = [];
        foreach ($raw as $item) {
            $item = (array) $item;
            $items[] = [
                'type' => $item['cabin_type'] ?? $item['type'] ?? null,
                'amount' => (float) ($item['fare'] ?? $item['amount'] ?? $item['price'] ?? 0),
                'merchant_id' => $item['merchant_id'] ?? null,
                'route_id' => $item['route_id'] ?? null,
                'vehicle_id' => $item['vehicle_id'] ?? $item['launch_id'] ?? null,
                'schedule_id' => $item['schedule_id'] ?? $item['trip_id'] ?? null,
                'hotel_id' => $item['hotel_id'] ?? null,
                'city_id' => $item['city_id'] ?? null,
            ];
        }

        return $items;
    }

    /**
     * Payment order.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function payment(Request $request)
    {
        $data = ['success' => false, 'message' => __('Your transaction cannot be instantiate.')];

        //validation rules
        $validator = Validator::make($request->all(), [
            'order_id' => 'bail|required|integer|exists:bookings,id',
            'payment_method' => 'bail|required|string'
        ]);

        //validation fails
        if ($validator->fails()) {
            $data['message'] = $validator->errors()->first();
        } else {
            $order = Booking::with(['payment'])->findOrFail($request->order_id);
            if (!is_null($order->payment)) {
                $payment = Payment::firstOrNew(['booking_id' => $order->id]);
                $payment->booking_id = $order->id;
                $payment->transaction_id = uniqid($order->id . '_', false);

                if ($payment->save()) {
                    $data['tran_id'] = $payment->transaction_id;
                    $data['success'] = true;
                    $data['message'] = __('Your payment has been successfully processed');
                }
            } else {
                $data['success'] = true;
                $data['message'] = __('Your payment has been successfully processed');
                $data['tran_id'] = $order->payment->transaction_id;
            }
        }

        return response()->json($data, $this->success);
    }
}
