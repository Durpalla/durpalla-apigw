<?php

namespace App\Http\Controllers\Api\v1;

use App\Constants\AppConst;
use Illuminate\Http\JsonResponse;
use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\Cabin;
use App\Models\CabinLock;
use App\Models\DeckFare;
use App\Http\Controllers\Controller;
use App\Services\TripService;
use App\Models\VehicleRoute;
use App\Models\VehicleSchedule;
use App\Models\PaymentCollector;
use App\Models\ScanLog;
use App\Services\CalculationService;
use App\Models\TicketPrint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\Payment;
use Illuminate\Support\Facades\Validator;
use App\Models\User;

class QuickBookController extends Controller
{
    private $calculation;
    private $trip;

    public function __construct(
        CalculationService $calculationService,
        TripService $tripService
    )
    {
        $this->calculation = $calculationService;
        $this->trip = $tripService;
        $this->status = 200;
        $this->success = 200;
        $this->middleware('auth:api');
    }

    public function findBookings(Request $request)
    {
        $data = ['success' => false, 'bookings' => [], 'message' => __('Nothing found')];
        $validator = Validator::make($request->all(), [
            'props' => 'required|string'
        ]);

        if ($validator->fails() == True) {
            $data['message'] = $validator->errors()->first();
        } else {
            $user = User::with(['meta', 'roles'])->find(auth()->user()->id);
            $bookings = Booking::with(['customer', 'payment', 'bookingItems.item.cabinType', 'bookingItems.launch', 'bookingItems.trip.startingPoint.ghat', 'bookingItems.trip.endingPoint.ghat', 'collections']);
            $singleTicket = false;
            $ticketID = '';
            if (preg_match("/^(01){1}[3456789]{1}(\d){8}$/", $request->props)) {
                $bookings->whereHas('customer', function ($q) use ($request) {
                    $q->where('mobile', $request->props);
                });
                $bookings->whereHas('bookingItems', function ($q) use ($request) {
                    $q->where('trip_date', date('Y-m-d'));
                });
            } else {
                $props = explode('-', $request->props);
                if (count($props)) {
                    $bookings->where('id', $props[0]);
                    if (!empty($props[1])) {
                        $singleTicket = true;
                        $bookings->whereHas('bookingItems', function ($q) use ($props) {
                            $q->where('id', $props[1]);
                        });
                        $bookings->with(['bookingItems' => function ($q) use ($props) {
                            $q->where('id', $props[1]);
                        }]);
                    }
                }
            }

            $bookings = $bookings->orderByDesc('created_at')->get();

            $responseArr = [];
            $ticket = [];
            if ($bookings) {
                $currentTime = time();
                foreach ($bookings as $booking) {
                    $dues = round(($booking->total_payable - $booking->payment['paid_amount']), 2);
                    if ($singleTicket && $booking['bookingItems']) {
                        $data['bookings'] = null;
                        $singleItem = collect($booking['bookingItems'])->where('id', $ticketID)->first();
                        foreach ($booking['bookingItems'] as $item) {
                            $row = [
                                'id' => $item['id'],
                                'booking_id' => $item['booking_id'],
                                'cabin_no' => ($item['item']) ? $item['item']['cabinType']['letter'] . '-' . $item['item']['cabin_no'] : '',
                                'cabin_type' => $item['booking_type'],
                                'fare' => $item['price'],
                                'discount' => $item['discount'],
                                'vat_visibility' => $item['launch']['merchant']['vat_visibility'],
                                'total_vat' => $this->calculation->calculateItemVat($item->toArray()),
                                'total_charge' => $this->calculation->calculateItemCharge($item->toArray()),
                                'total_discount' => $this->calculation->calculateItemDiscount($item->toArray()),
                                'total_amount' => $this->calculation->calculateItemTotal($item->toArray()),
                                'is_ac' => ($item['item']) ? $item['item']['cabinType']['is_ac'] : '',
                                'vehicle_name' => $item['trip']['launch']['name'],
                                'route_name' => $item['trip']['route']['route_name'],
                                'schedule_date' => date('d F Y', strtotime($item['trip_date'])),
                                'leaving_time' => $item['trip']['leaving_at'],
                                'boarding_point' => json_decode($item['boarding_point']),
                                'passenger' => json_decode($item['passenger']),
                                'from' => ($item->trip['schedule_type'] == 'reverse') ? $item['trip']['endingPoint']['ghat']['name'] : $item['trip']['startingPoint']['ghat']['name'],
                                'to' => ($item->trip['schedule_type'] == 'reverse') ? $item['trip']['startingPoint']['ghat']['name'] : $item['trip']['endingPoint']['ghat']['name'],
                                'printable' => false,
                                'printed' => $item->printed,
                                'reprintable' => false,
                                'status' => $item->status,
                                'dues' => 0,
                                'validity' => (strtotime($item->trip['leaving_at']) <= $currentTime && (strtotime($item->trip['leaving_at'])+($item->trip['operation_hour'] * 60 * 60)) >= $currentTime) ? 'valid' : 'invalid'
                            ];
                            if ($item['trip']['schedule_type'] == 'reverse') {
                                $irow['route_name'] = $item['trip']['endingPoint']['ghat']['name'] . ' - ' . $item['trip']['startingPoint']['ghat']['name'];
                            }
                            if ($dues <= 0) {
                                if (($item['status'] == 1) && (date('Y-m-d', strtotime($item['trip_date'])) == date('Y-m-d')) && (!in_array($item['id'], $cancellations)) && !$item['printed'] && $booking->status == 'COMPLETE') {
                                    $row['printable'] = true;
                                }
                                if (($item['status'] == 1) && (date('Y-m-d', strtotime($item['trip_date'])) == date('Y-m-d')) && (!in_array($item['id'], $cancellations)) && ($item['printed'] > 0 && $item['printed'] < 5 && $booking->status == 'COMPLETE')) {
                                    $row['reprintable'] = true;
                                }
                            } else {
                                $row['dues'] = $dues;
                            }
                            if ($singleItem->id == $item->id) {
                                array_push($ticket, $row);
                            }
                        }
                    } else {
                        $validity = 'invalid';
                        $items = ['cabin' => [], 'seat' => [], 'deck' => 0];
                        if($booking->bookingItems) {
                            $booking->bookingItems->each(function($item, $key) use($currentTime, &$validity, &$items) {
                                if($item->booking_type != 'deck') {
                                    $cabinNo = ($item->item['cabinType']) ? $item->item['cabinType']['letter'] . '-' : '';
                                    $cabinNo .= $item->item['cabin_no'];
                                    $items[$item->booking_type][] = $cabinNo;
                                } else {
                                    $passenger = json_decode($item->passenger);
                                    $items['deck'] += ($passenger) ? $passenger->person : 1;
                                }
                                if((strtotime($item->trip['leaving_at'])+($item->trip['operation_hour'] * 60 * 60)) >= $currentTime) {
                                    $validity = 'valid';
                                }
                            });
                        }
                        $nid = null;
                        if($booking->customer['meta'] && $booking->customer['meta']['nid_no'] && $user->meta && $user->meta['nid_visible_until'] >= now()) {
                            $nid = [
                                'nid_no' => $booking->customer['meta']['nid_no'],
                                'front' => ($booking->customer['meta']['nid_photo']) ? asset('nid/' . $booking->customer['meta']['nid_photo']) : '',
                                'back' => ($booking->customer['meta']['nid_back_side']) ? asset('nid/' . $booking->customer['meta']['nid_back_side']) : ''
                            ];
                        }
                        array_push($responseArr, [
                            'id' => $booking->id,
                            'pnr' => $booking->id,
                            'qr' => asset('qrs/' . $booking->id . '.png'),
                            'booking_date' => date('Y-m-d H:i:s', strtotime($booking->created_at)),
                            'payment_status' => $booking->payment['status'],
                            'total_amount' => $booking->total_amount,
                            'vat_total' => $booking->vat_total,
                            'charge_total' => $booking->charge_total,
                            'status' => $booking->status,
                            'dues' => $dues,
                            'validity' => $validity,
                            'items' => $items,
                            'nid' => $nid
                        ]);
                    }
                }

                $data['bookings'] = ($responseArr) ? $responseArr : null;
                $data['ticket'] = ($ticket) ? $ticket : null;
                $data['message'] = __('Booking found');
                $data['success'] = true;
            } else {
                $data['bookings'] = null;
                $data['ticket'] = null;
            }
        }

        return response()->json($data, $this->success);
    }

    public function qrScan(Request $request)
    {
        $data = ['success' => false, 'booking' => [], 'message' => ''];
        $validator = Validator::make($request->all(), [
            'props' => 'required',
            'type' => 'nullable|string'
        ]);

        if ($validator->fails() == True) {
            $data['message'] = $validator->errors()->first();
        } else {
            $props = explode('-', $request->props);
            $singleTicket = false;
            $ticketID = '';
            if (count($props)) {
                $booking = Booking::with(['customer', 'payment', 'bookingItems.item.cabinType', 'bookingItems.launch.merchant'])->find($props[0]);
                if (!empty($props[1])) {
                    $singleTicket = true;
                    $ticketID = $props[1];
                }
                $responseArr = [];
                $ticket = [];
                if ($booking) {
                    $dues = round(($booking->total_payable - $booking->payment->paid_amount), 2);
                    if ($request->type == 'scan') {
                        ScanLog::create([
                            'user_id' => Auth::user()->id,
                            'booking_id' => $booking->id,
                            'ticket_id' => (int)$ticketID
                        ]);
                    }

                    $cancellations = [];
                    if ($booking->cancellations) {
                        foreach ($booking->cancellations as $cancellation) {
                            $cancellations = array_merge_recursive($cancellations, explode(',', $cancellation->items));
                        }
                    }

                    if ($singleTicket && $ticketID && $booking->bookingItems) {
//                      $item = collect($booking['bookingItems'])->where('id', $ticketID)->first();
                        foreach ($booking->bookingItems as $item) {
                            $passenger = json_decode($item->passenger);
                            $persons = ($passenger) ? $passenger->person : 1;
                            $passengerDetails = [
                                'name' => $passenger ? $passenger->name : $booking->customer->name,
                                'mobile' => $passenger ? $passenger->mobile : $booking->customer->mobile,
                                'person' => $passenger ? $passenger->person : 1,
                                'for' => ($booking->officer->hasRole('supervisor')) ? 'self' : 'other'
                            ];
                            $row = [
                                'id' => $item['id'],
                                'booking_id' => $item['booking_id'],
                                'cabin_no' => ($item['item']) ? $item['item']['cabinType']['letter'] . '-' . $item['item']['cabin_no'] : '',
                                'cabin_desc' => ucfirst($item['booking_type']),
                                'cabin_type' => $item['booking_type'],
                                'fare' => ($item['booking_type'] == 'deck') ? $item['price'] / $persons : $item['price'],
                                'discount' => $item['discount'],
                                'vat_visibility' => $item['launch']['merchant']['vat_visibility'],
                                'total_vat' => $this->calculation->calculateItemVat($item->toArray()),
                                'total_charge' => $this->calculation->calculateItemCharge($item->toArray()),
                                'total_discount' => $this->calculation->calculateItemDiscount($item->toArray()),
                                'total_amount' => $this->calculation->calculateItemTotal($item->toArray()),
                                'is_ac' => ($item['item']) ? $item['item']['cabinType']['is_ac'] : '',
                                'vehicle_name' => $item['trip']['launch']['name'],
                                'route_name' => $item['trip']['route']['route_name'],
                                'schedule_date' => date('d F Y', strtotime($item['trip_date'])),
                                'leaving_time' => $item['trip']['leaving_at'],
                                'boarding_point' => json_decode($item['boarding_point']),
                                'passenger' => (object) $passengerDetails,
                                'from' => ($item->trip['schedule_type'] == 'reverse') ? $item['trip']['endingPoint']['ghat']['name'] : $item['trip']['startingPoint']['ghat']['name'],
                                'to' => ($item->trip['schedule_type'] == 'reverse') ? $item['trip']['startingPoint']['ghat']['name'] : $item['trip']['endingPoint']['ghat']['name'],
                                'printable' => false,
                                'printed' => $item->printed,
                                'reprintable' => false,
                                'status' => $item->status
                            ];
                            if ($item['booking_type'] != 'deck') {
                                $row['cabin_desc'] .= ' ' . $item['item']['cabinType']['name'];
                                if ($item['item']['cabinType']['is_ac']) {
                                    $row['cabin_desc'] .= ' (AC)';
                                } else {
                                    $row['cabin_desc'] .= ' (NonAC)';
                                }
                            }
                            if ($item['trip']['schedule_type'] == 'reverse') {
                                $irow['route_name'] = $item['trip']['endingPoint']['ghat']['name'] . ' - ' . $item['trip']['startingPoint']['ghat']['name'];
                            }
                            if ($dues <= 0) {
                                if (($item['status'] == 1) && (date('Y-m-d', strtotime($item['trip_date'])) == date('Y-m-d')) && (!in_array($item['id'], $cancellations)) && !$item['printed'] && $booking->status == 'COMPLETE') {
                                    $row['printable'] = true;
                                }
                                if (($item['status'] == 1) && (date('Y-m-d', strtotime($item['trip_date'])) == date('Y-m-d')) && (!in_array($item['id'], $cancellations)) && ($item['printed'] > 0 && $item['printed'] < 5 && $booking->status == 'COMPLETE')) {
                                    $row['reprintable'] = true;
                                }
                            }
                            if ($item['id'] == $ticketID) {
                                $ticket = $row;
                            }
                        }
                    } else {
                        $responseArr['id'] = $booking->id;
                        $responseArr['pnr'] = $booking->id;
                        $responseArr['qr'] = asset('qrs/' . $booking->id . '.png');
                        $responseArr['booking_date'] = date('Y-m-d H:i:s', strtotime($booking->created_at));
                        $responseArr['payment_status'] = $booking->payment['status'];
                        $responseArr['total_amount'] = $booking->total_amount;
                        $responseArr['total_discount'] = $booking->total_discount;
                        $responseArr['vat_amount'] = $booking->vat_amount;
                        $responseArr['vat_total'] = $booking->vat_total;
                        $responseArr['charge_amount'] = $booking->charge_amount;
                        $responseArr['charge_total'] = $booking->charge_total;
                        $responseArr['total_payable'] = abs(($booking->total_amount + $booking->vat_total + $booking->charge_total - $booking->total_discount));
                        // $responseArr['payment'] = $booking->payment;
                        $responseArr['paid_amount'] = round($booking->payment->paid_amount, 2);
                        $responseArr['dues'] = ($dues <= 0) ? 0 : $dues;
                        $responseArr['transaction_id'] = $booking->payment['transaction_id'];
                        $responseArr['cancellable'] = false;
                        $responseArr['status'] = $booking->status;
                        $responseArr['items'] = [];

                        foreach ($booking->bookingItems as $item) {
                            $passenger = json_decode($item->passenger);
                            $persons = ($passenger) ? $passenger->person : 1;
                            $passengerDetails = [
                                'name' => $passenger ? $passenger->name : $booking->customer->name,
                                'mobile' => $passenger ? $passenger->mobile : $booking->customer->mobile,
                                'person' => $passenger ? $passenger->person : 1,
                                'for' => ($booking->officer->hasRole('supervisor')) ? 'self' : 'other'
                            ];
                            $row = [
                                'id' => $item['id'],
                                'booking_id' => $item['booking_id'],
                                'cabin_no' => ($item['item']) ? $item['item']['cabinType']['letter'] . '-' . $item['item']['cabin_no'] : '',
                                'cabin_desc' => ucfirst($item['booking_type']),
                                'cabin_type' => $item['booking_type'],
                                'fare' => $item['price'],
                                'vat_visibility' => $item['launch']['merchant']['vat_visibility'],
                                'total_vat' => $this->calculation->calculateItemVat($item->toArray()),
                                'total_charge' => $this->calculation->calculateItemCharge($item->toArray()),
                                'total_discount' => $this->calculation->calculateItemDiscount($item->toArray()),
                                'total_amount' => $this->calculation->calculateItemTotal($item->toArray()),
                                'discount' => $item['discount'],
                                'is_ac' => ($item['item']) ? $item['item']['cabinType']['is_ac'] : '',
                                'vehicle_name' => $item['trip']['launch']['name'],
                                'route_name' => $item['trip']['route']['route_name'],
                                'schedule_date' => date('d F Y', strtotime($item['trip_date'])),
                                'leaving_time' => $item['trip']['leaving_at'],
                                'leaving_time_formated' => date('h:i A', strtotime($item['trip']['leaving_at'])),
                                'boarding_point' => json_decode($item['boarding_point']),
                                'passenger' => (object) $passengerDetails,
                                'from' => ($item['trip']['schedule_type'] == 'reverse') ? $item['trip']['endingPoint']['ghat']['name'] : $item['trip']['startingPoint']['ghat']['name'],
                                'to' => ($item['trip']['schedule_type'] == 'reverse') ? $item['trip']['startingPoint']['ghat']['name'] : $item['trip']['endingPoint']['ghat']['name'],
                                'printable' => false,
                                'printed' => $item['printed'],
                                'reprintable' => false,
                                'status' => $item['status']
                            ];
                            if ($item['booking_type'] != 'deck') {
                                $row['cabin_desc'] .= ' ' . $item['item']['cabinType']['name'];
                                if ($item['item']['cabinType']['is_ac']) {
                                    $row['cabin_desc'] .= ' (AC)';
                                } else {
                                    $row['cabin_desc'] .= ' (NonAC)';
                                }
                            }

                            if ($item['trip']['schedule_type'] == 'reverse') {
                                $irow['route_name'] = $item['trip']['endingPoint']['ghat']['name'] . ' - ' . $item['trip']['startingPoint']['ghat']['name'];
                            }
                            if ($dues <= 0) {
                                if (($item['status'] == 1) && (date('Y-m-d', strtotime($item['trip_date'])) == date('Y-m-d')) && (!in_array($item['id'], $cancellations)) && !$item['printed'] && $booking->status == 'COMPLETE') {
                                    $row['printable'] = true;
                                }
                                if (($item['status'] == 1) && (date('Y-m-d', strtotime($item['trip_date'])) == date('Y-m-d')) && (!in_array($item['id'], $cancellations)) && ($item['printed'] > 0 && $item['printed'] < 5 && $booking->status == 'COMPLETE')) {
                                    $row['reprintable'] = true;
                                }
                            }
                            array_push($responseArr['items'], $row);
                        }
                    }
                    $data['booking'] = ($responseArr) ? $responseArr : null;
                    $data['message'] = __('Booking found');
                    $data['success'] = true;
                    $data['ticket'] = ($ticket) ? $ticket : null;
                } else {
                    $data['message'] = __('Nothing found');
                    $data['booking'] = null;
                    $data['ticket'] = null;
                }
            } else {
                $data['message'] = __('Nothing found');
                $data['booking'] = null;
                $data['ticket'] = null;
            }
        }

        return response()->json($data, $this->success);
    }

    public function getBookingByID(Request $request, int $booking_id)
    {
        $data = ['success' => true, 'booking' => ['items' => []], 'message' => __('Booking not found')];

        $booking = Booking::with(['customer', 'payment', 'bookingItems.item.cabinType', 'bookingItems.trip', 'bookingItems.launch.merchant'])->find($booking_id);

        if($booking) {
            $data['message'] = 'Booking found';
            $data['booking']['booking_id'] = $booking->id;
            foreach ($booking->bookingItems as $bookingItem) {
                $passenger = json_decode($bookingItem->passenger);
                $persons = ($passenger) ? $passenger->person : 1;
                $passengerDetails = [
                    'name' => $passenger ? $passenger->name : $booking->customer->name,
                    'mobile' => $passenger ? $passenger->mobile : $booking->customer->mobile,
                    'person' => $passenger ? $passenger->person : 1,
                    'for' => ($booking->officer->hasRole('supervisor')) ? 'self' : 'other'
                ];
                array_push($data['booking']['items'], [
                    'cabin_id' => $bookingItem->cabin_id,
                    'cabin_no' => ($bookingItem->booking_type != 'deck') ? $bookingItem->item->cabin_no : null,
                    'cabin_row' => ($bookingItem->booking_type != 'deck') ? $bookingItem->item->cabin_row : null,
                    'cabin_type' => $bookingItem->booking_type,
                    'cabin_type_id' => ($bookingItem->booking_type != 'deck') ? $bookingItem->item->type_id : null,
                    'capacity' => ($bookingItem->booking_type != 'deck') ? $bookingItem->item->passenger_capacity : null,
                    'cabin_floor' => ($bookingItem->booking_type != 'deck') ? $bookingItem->item->floor : null,
                    'cabin_is_ac' => ($bookingItem->booking_type != 'deck') ? $bookingItem->item->cabinType->is_ac : null,
                    'cabin_type_name' => ($bookingItem->item->cabinType) ? $bookingItem->item->cabinType->name : null,
                    'description' => ($bookingItem->booking_type != 'deck') ? ucfirst($bookingItem->booking_type) . ' ' . $bookingItem->item->cabinType->letter . '-' . $bookingItem->item->cabin_no : 'Deck X ' . $passenger->person,
                    'fare' => ($bookingItem->booking_type == 'deck') ? $bookingItem->price/$persons : $bookingItem->price,
                    'vat_visibility' => $bookingItem->launch['merchant']['vat_visibility'],
                    'total_vat' => $this->calculation->calculateItemVat($bookingItem->toArray()),
                    'total_charge' => $this->calculation->calculateItemCharge($bookingItem->toArray()),
                    'total_discount' => $this->calculation->calculateItemDiscount($bookingItem->toArray()),
                    'total_amount' => $this->calculation->calculateItemTotal($bookingItem->toArray()),
                    'vehicle_id' => $bookingItem->vehicle_id,
                    'vehicle_name' => $bookingItem->launch->name,
                    'status' => $bookingItem->status,
                    'trip_date' => date('Y-m-d H:i:s', strtotime($bookingItem->trip->leaving_at)),
                    'trip_id' => $bookingItem->trip_id,
                    'merchant_id' => $bookingItem->launch->merchant_id,
                    'route_id' => $bookingItem->trip->route_id,
                    'is_honorium' => $bookingItem->is_honorium,
                    'honorium_charge' => $bookingItem->honorium_charge,
                    'incentive' => $bookingItem->incentive,
                    'incentive_type' => $bookingItem->incentive_type,
                    'vat_amount' => $bookingItem->vat_amount,
                    'vat_applicable_to' => $bookingItem->vat_applicable_to,
                    'discount' => $bookingItem->discount,
                    'boarding_point' => json_decode($bookingItem->boarding_point),
                    'passenger' => (object) $passengerDetails,
                ]);
            }
        }

        return response()->json($data, $this->success);
    }

    public function printAll(Request $request)
    {
        $data = ['success' => false, 'message' => __('Cannot handle request')];
        $validator = Validator::make($request->all(), [
            'booking_id' => 'bail|required|numeric|exists:bookings,id',
            'items' => 'bail|nullable'
        ]);

        if ($validator->fails() == True) {
            $data['message'] = $validator->errors()->first();
        } else {
            $bookingItems = BookingItem::where('booking_id', $request->booking_id)->get();
            $bookingIds = $bookingItems->map(function($item, $key) {
                return $item->id;
            });
            if ($bookingIds) {
                DB::table('booking_items')->whereIn('id', $bookingIds)
                    ->update([
                        'printed' => +1
                    ]);
                $data['success'] = true;
                $data['message'] = __('All items printed');
            }
        }
        return response()->json($data, $this->success);
    }

    public function printConfirm(Request $request)
    {
        $data = ['success' => false, 'message' => ''];
        $validator = Validator::make($request->all(), [
            'booking_id' => 'bail|required|numeric|exists:bookings,id',
            'ticket_id' => 'bail|required|exists:booking_items,id'
        ]);

        if ($validator->fails() == True) {
            $data['message'] = $validator->errors()->first();
        } else {
            $item = BookingItem::where(['booking_id' => $request->booking_id, 'id' => $request->ticket_id])->first();

            if ($item && $item->trip_date == date('Y-m-d') && $item->status == 1) {
                $item->printed += 1;
                if ($item->save()) {
                    TicketPrint::create([
                        'booking_id' => $item->booking_id,
                        'booking_item_id' => $item->id,
                        'supervisor_id' => Auth::user()->id
                    ]);
                    $data['success'] = true;
                    $data['message'] = __('Ticket print confirmed');
                }
            } else {
                $data['message'] = __('Ticket not printable');
            }
        }

        return response()->json($data, $this->success);
    }

    public function rePrintRequest(Request $request)
    {
        $data = ['success' => false, 'message' => ''];
        $validator = Validator::make($request->all(), [
            'booking_id' => 'bail|required|numeric|exists:bookings,id',
            'ticket_id' => 'bail|required|exists:booking_items,id'
        ]);

        if ($validator->fails() == True) {
            $data['message'] = $validator->errors()->first();
        } else {
            $item = BookingItem::where(['booking_id' => $request->booking_id, 'id' => $request->ticket_id])->first();

            if ($item && $item->trip_date == date('Y-m-d') && $item->status == 1 && $item->printed > 0 && $item->printed < 5) {
                $code = mt_rand(100000, 999999);
                $item->otp_code = $code;
                $item->save();
                sendSMS([
                    'mobile' => $item->customer->mobile,
                    'message' => 'Your otp code is ' . $code
                ]);
                $data['success'] = true;
                $data['message'] = __('An OTP sent to customers mobile');
            } else {
                $data['message'] = __('Ticket not printable');
            }
        }

        return response()->json($data, $this->success);
    }

    public function rePrintConfirm(Request $request)
    {
        $data = ['success' => false, 'data' => [], 'message' => ''];
        $validator = Validator::make($request->all(), [
            'booking_id' => 'bail|required|exists:bookings,id',
            'ticket_id' => 'bail|required|exists:booking_items,id',
            'otp_code' => 'bail|required'
        ]);

        if ($validator->fails() == True) {
            $data['message'] = $validator->errors()->first();
        } else {
            $item = BookingItem::where(['booking_id' => $request->booking_id, 'id' => $request->ticket_id])->first();

            if ($item && ($item->otp_code == $request->otp_code) && ($item->trip_date == date('Y-m-d')) && $item->printed < 5) {
                $item->printed += 1;
                $item->save();
                TicketPrint::create([
                    'booking_id' => $item->booking_id,
                    'booking_item_id' => $item->id,
                    'supervisor_id' => Auth::user()->id
                ]);
                $data['success'] = true;
                $data['message'] = __('Reprint confirmed');
            } else {
                $data['message'] = __('Your otp code is not valid');
            }
        }

        return response()->json($data, $this->success);
    }

    public function search(Request $request)
    {
        $results = null;
        $user = Auth::user();
        $yesterday = date('Y-m-d', strtotime('yesterday'));
        $today = date('Y-m-d');
        $query = VehicleSchedule::with(['merchant', 'route', 'startingPoint.ghat', 'endingPoint.ghat', 'boardingVias.ghat', 'startFrom', 'stopTo', 'cabinMappings', 'seatMappings', 'locks', 'bookingItems', 'ticketBookings'])
            ->where('status', AppConst::SCHEDULE_ACTIVE);

        $trip_date = ($request->trip_date) ? $request->trip_date : date('Y-m-d');
        if ($user->hasRole('supervisor')) {
            $launchIds = [];
            if ($user->vehicles) {
                foreach ($user->vehicles as $mapping) {
                    array_push($launchIds, $mapping->vehicle_id);
                }
            }
            $query->whereIn('vehicle_id', $launchIds);
        }

        if ($trip_date) {
            $date = date('Y-m-d', strtotime($trip_date));
            $query->where('schedule_date', $date);
        }

        if (!empty($request->route_id)) {
            $query->where('route_id', $request->route_id);
        }

        if (!empty($request->type)) {
            $query->where('schedule_type', $request->type);
        }

        $results = $query->orderBy('schedule_date', 'asc')->get();

        $trips = $results->map(function($trip, $key) {
            return $this->trip->formatTripList($trip);
        });

        return response()->json(['success' => true, 'data' => $trips], $this->success);
    }

    /**
     * Display a tip details
     * Parameter is trip id
     * @return \Illuminate\Http\JsonResponse
     */
    public function trip(Request $request, $id)
    {
        $layout = collect(VehicleSchedule::with(['route', 'decks.departureFrom.ghat', 'decks.departureTo.ghat', 'boardingVias.ghat', 'startFrom', 'stopTo', 'mappings.cabinType', 'vehicle', 'merchant'])
            ->where('id', $id)
            ->get())
            ->map(function($trip, $key) use($request) {
                return $this->trip->formatTriplayout($trip, $request->floor);
            })->first();
        return response()->json(['success' => true, 'data' => $layout], $this->success);
    }

    public function routes()
    {
        $query = VehicleRoute::with(['startingPoint.ghat', 'endingPoint.ghat']);

        if (isset($_GET['term'])) {
            $term = $_GET['term'];
            $query->where('route_name', 'LIKE', '%' . $term . '%');
        }

        $query = $query->paginate(15);

        $results = [];

        if ($query) {
            foreach ($query as $q) {
                $row['id'] = $q->id;
                $row['name'] = $q->route_name;

                array_push($results, $row);
            }
        }

        return response()->json(['results' => $results], 200);
    }

    public function addToCart(Request $request)
    {
        $data = ['success' => false, 'message' => __('Your item cannot be locked')];
        $user = Auth::user();
        //validation rules
        $validator = Validator::make($request->all(), [
            'item_id' => 'bail|required|integer|exists:cabins,id',
            'trip_id' => 'bail|required|integer|exists:vehicle_schedules,id',
            'customer_token' => 'bail|required|string'
        ]);

        //validation fails
        if ($validator->fails())
            return response()->json(['success' => false, 'message' => $validator->errors()->first()], $this->success);

        $schedule = VehicleSchedule::with(['startingPoint.ghat', 'endingPoint.ghat', 'boardingVias.ghat', 'discounts'])->findOrFail($request->trip_id);

        if ((strtotime($schedule->leaving_at) + ($schedule->operation_hour * 60 * 60)) >= time()) {
            $item = Cabin::with(['launch.merchant', 'cabinType', 'books' => function ($query) use ($schedule) {
                $query->where(['trip_id' => $schedule->id])->whereIn('status', [0, 1]);
            }, 'locks' => function ($query) use ($schedule) {
                $query->where('trip_id', $schedule->id);
            }, 'mapping' => function ($q) use ($schedule) {
                $q->where('schedule_id', $schedule->id);
            }])
                ->findOrFail($request->item_id);

            if ($item->books && $item->books->count() > 0) {
                $data['message'] = __('Your item is already booked');
            } elseif ($item->locks && $item->locks->count() > 0) {
                $data['message'] = __('Your item is already been locked');
            } else {
                try {
                    $discounted = 0;
                    if ($schedule->discounts) {
                        if ($user->type == 'admin') {
                            $userType = AppConst::OWNER;
                        } else {
                            $userType = 'merchant';
                        }
                        foreach ($schedule->discounts as $discount) {
                            $calculated = ($discount->type == 'p') ? ($item->fare * ($discount->amount / 100)) : $discount->amount;
                            if (($userType == $discount->applicable_to) || $discount->applicable_to == 'both') {
                                switch ($item->type) {
                                    case 'cabin':
                                        $discounted += ($discount->is_cabin) ? $calculated : 0;
                                        break;
                                    case 'seat':
                                        $discounted += ($discount->is_seat) ? $calculated : 0;
                                        break;
                                    case 'deck':
                                        $discounted += ($discount->is_deck) ? $calculated : 0;
                                        break;
                                }
                            }
                        }
                    }
                    $lockItem = CabinLock::create([
                        'cabin_id' => $item->id,
                        'customer_token' => ( string )$request->customer_token,
                        'trip_id' => ( int )$request->trip_id
                    ]);
                    $data['success'] = true;
                    $data['message'] = __('The item has been successfully locked');
                    $vat_applicable_to = $item['launch']['merchant']['vat_applicable_to'];
                    $description = $item['cabinType']['name'] . ' - ' . strtoupper($item['cabinType']['letter']). $item['cabin_no'];
                    $description .= ($item['cabinType']['is_ac']) ? '(AC)' : '(Non AC)';
                    $vat_amount = ($vat_applicable_to == 'customer') ? abs(getOption('vat_amount', 0)) : 0;
                    $charge_amount = abs(getOption('service_charge_counter', 0));
                        $vat_total = $this->calculation->calculateItemVat(['vat_amount' => $vat_amount, 'price' => $item->fare]);
                    $charge_total = ($user->type == 'admin') ? $this->calculation->calculateItemCharge(['charge_amount' => $charge_amount, 'price' => $item->fare]) : 0;
                    $total_amount = round(($item->fare + $vat_total + $charge_total - $discounted), 2);
                    $data['item'] = [
                        'trip_id' => $lockItem->trip_id,
                        'trip_date' => date('Y-m-d h:i:s', strtotime($schedule->leaving_at)),
                        'vehicle_id' => $item->vehicle_id,
                        'merchant_id' => $item->launch['merchant_id'],
                        'route_id' => $schedule->route_id,
                        'vehicle_name' => $item->launch['name'],
                        'route_name' => $schedule->startingPoint['ghat']['name'] . ' - ' . $schedule->endingPoint['ghat']['name'],
                        'cabin_type_id' => $item->type_id,
                        'cabin_floor' => $item->floor,
                        'cabin_no' => ($item['type'] == 'cabin') ? $item['cabinType']['letter'] . '-' . $item['cabin_no'] : $item['cabin_no'],
                        'description' => $description,
                        'item_type' => ($item['cabinType']) ? $item['cabinType']['name'] : null,
                        'cabin_type_name' => ($item['cabinType']) ? $item['cabinType']['name'] : null,
                        'cabin_id' => $item->id,
                        'cabin_type' => $item->type,
                        'fare' => $item->fare,
                        'total_vat' => round($vat_total, 2),
                        'total_charge' => round($charge_total, 2),
                        'vat_amount' => round($vat_amount, 2),
                        'charge_amount' => round($charge_amount, 2),
                        'discount' => round($discounted, 2),
                        'total_amount' => round($total_amount, 2),
                        'vat_applicable_to' => $vat_applicable_to,
                        'cabin_is_ac' => $item->cabinType['is_ac'],
                        'capacity' => $item->passenger_capacity,
                        'status' => 2,
                        'boardingPoint' => [],
                        'stoppages' => [],
                        'passenger' => ['type' => 'self', 'name' => '', 'mobile' => '', 'person' => $item->passenger_capacity],
                        'is_honorium' => (int)$item->mapping['honorium'],
                        'honorium_charge' => abs($schedule->launch['merchant']['honorium_charge']),
                        'honorium_type' => abs($schedule->launch['merchant']['honorium_type']),
                        'incentive' => 0,
                        'incentive_type' => 'percent'
                    ];
                    if ($user->hasRole('supervisor')) {
                        $mapping = collect($user->supervisorMappings)->where('vehicle_id', $item->vehicle_id)->first();
                        $data['item']['incentive'] = $mapping->supervisor_incentive;
                        $data['item']['incentive_type'] = $mapping->incentive_type;
                    }

                    if ($schedule->schedule_type == 'reverse') {
                        $data['item']['route_name'] = $schedule->endingPoint['ghat']['name'] . ' - ' . $schedule->startingPoint['ghat']['name'];
                    }

                    //push stoppages
                    if ($schedule->schedule_type == 'reverse') {
                        $data['item']['boardingPoint'] = ['id' => $schedule->endingPoint['id'], 'name' => $schedule->endingPoint['ghat']['name']];
                        array_push($data['item']['stoppages'], ['id' => $schedule->endingPoint['id'], 'name' => $schedule->endingPoint['ghat']['name']]);
                    } else {
                        $data['item']['boardingPoint'] = ['id' => $schedule->startingPoint['id'], 'name' => $schedule->startingPoint['ghat']['name']];
                        array_push($data['item']['stoppages'], ['id' => $schedule->startingPoint['id'], 'name' => $schedule->startingPoint['ghat']['name']]);
                    }

                    if ($schedule->boardingVias) {
                        foreach ($schedule->boardingVias as $stoppage) {
                            array_push($data['item']['stoppages'], ['id' => $stoppage['id'], 'name' => $stoppage['ghat']['name']]);
                        }
                    }
                } catch (\Exception $e) {
                    $data['message'] = __('Something happened wrong. please try again later.');
                }
            }
        } else {
            $data['message'] = __('Booking operation hour expired');
        }
        return response()->json($data, $this->success);
    }

    public function addToCartDeck(Request $request)
    {
        $data = ['success' => false, 'message' => __('Your item cannot be locked')];
        //validation rules
        $validator = Validator::make($request->all(), [
            'deck_id' => 'bail|required|integer|exists:deck_fares,id',
            'trip_id' => 'bail|required|integer|exists:vehicle_schedules,id',
            'passengers' => 'bail|required|integer'
        ]);

        //validation fails
        if ($validator->fails())
            return response()->json(['success' => false, 'message' => $validator->errors()->first()], $this->success);

        $schedule = VehicleSchedule::with(['launch', 'startingPoint', 'endingPoint', 'boardingVias'])->findOrFail($request->trip_id);

        if ((strtotime($schedule->leaving_at) + ($schedule->operation_hour * 60 * 60)) <= time()) {
            $item = DeckFare::find($request->deck_id);

            if ($item) {
                $vat_applicable_to = $schedule->launch['merchant']['vat_applicable_to'];
                $vat_amount = abs(getOption('vat_amount'));
                $vat = ($vat_applicable_to == 'customer') ? abs(($item->fare * $request->passengers) * ($vat_amount / 100)) : 0;
                $service_charge_counter = abs(getOption('service_charge_counter'));
                $service_charge = 0;
                if (Auth::user()->type != 'merchant') {
                    $service_charge = abs(($item->fare * $request->passengers) * ($service_charge_counter / 100));
                }
                $discounted = 0;

                if ($schedule->discounts) {
                    if (Auth::user()->type == 'admin') {
                        $userType = AppConst::OWNER;
                    } else {
                        $userType = 'merchant';
                    }
                    foreach ($schedule->discounts as $discount) {
                        $calculated = ($discount->type == 'p') ? (($item->fare * $request->passengers) * ($discount->amount / 100)) : ($discount->amount * $request->passengers);
                        if (($discount->is_deck) && (($userType == $discount->applicable_to) || $discount->applicable_to == 'both')) {
                            $discounted += $calculated;
                        }
                    }
                }
                $data['item'] = [
                    'cabin_type' => 'deck',
                    'trip_id' => $schedule->id,
                    'trip_date' => date('Y-m-d H:i:s', strtotime($schedule->schedule_date)),
                    'vehicle_id' => $schedule->vehicle_id,
                    'merchant_id' => $schedule->launch['merchant_id'],
                    'route_id' => $schedule->route_id,
                    'vehicle_name' => $schedule->launch['name'],
                    'route_name' => $schedule->startingPoint['ghat']['name'] . ' - ' . $schedule->endingPoint['ghat']['name'],
                    'cabin_no' => $item->id,
                    'cabin_id' => $item->id,
                    'cabin_type_id' => '',
                    'cabin_floor' => 0,
                    'item_type' => 'deck',
                    'cabin_type_name' => 'deck',
                    'cabin_is_ac' => 0,
                    'vat_amount' => $vat_amount,
                    'charge_amount' => $service_charge_counter,
                    'vat_applicable_to' => $vat_applicable_to,
                    'fare' => round(($item->fare * $request->passengers), 2),
                    'total_passenger' => $request->passenger,
                    'total_vat' => round($vat, 2),
                    'total_charge' => round($service_charge, 2),
                    'discount' => $discounted,
                    'status' => 2,
                    'capacity' => $request->passengers,
                    'passenger' => ['name' => '', 'mobile' => '', 'person' => $request->passengers],
                    'stoppages' => [],
                    'boardingPoint' => null,
                    'is_honorium' => 0,
                    'honorium_charge' => 0,
                    'honorium_type' => 'percent',
                    'incentive' => 0,
                    'incentive_type' => 'percent'
                ];
                $user = Auth::user();
                if ($user->hasRole('supervisor')) {
                    $mapping = collect($user->supervisorMappings)->where('vehicle_id', $item->vehicle_id)->first();
                    $data['item']['incentive'] = $mapping->supervisor_incentive;
                    $data['item']['incentive_type'] = $mapping->incentive_type;
                }

                //push stoppages
                if ($schedule->schedule_type == 'reverse') {
                    $data['item']['route_name'] = $schedule->endingPoint['ghat']['name'] . ' - ' . $schedule->startingPoint['ghat']['name'];
                    $data['item']['boardingPoint'] = ['id' => $schedule->endingPoint['ghat']['id'], 'name' => $schedule->endingPoint['ghat']['name']];
                    array_push($data['item']['stoppages'], ['id' => $schedule->endingPoint['ghat']['id'], 'name' => $schedule->endingPoint['ghat']['name']]);
                } else {
                    $data['item']['boardingPoint'] = ['id' => $schedule->startingPoint['ghat']['id'], 'name' => $schedule->startingPoint['ghat']['name']];
                    array_push($data['item']['stoppages'], ['id' => $schedule->startingPoint['ghat']['id'], 'name' => $schedule->startingPoint['ghat']['name']]);
                }

                if ($schedule->boardingVias) {
                    foreach ($schedule->boardingVias as $stoppage) {
                        array_push($data['item']['stoppages'], ['id' => $stoppage['id'], 'name' => $stoppage['name']]);
                    }
                }

                $data['success'] = true;
                $data['message'] = __('Your deck ticket has been added successfully');
            }
        } else {
            $data['message'] = __('Booking operation hour expired');
        }
        return response()->json($data, $this->success);
    }

    /**
     * Confirm order.
     *
     * @return JsonResponse
     */
    public function confirm(Request $request)
    {
        $data = ['success' => false, 'message' => __('Your booking request is not valid')];

        $items = json_decode(str_replace("\\", "", $request->items));
        try {
            $itemsTobeValidated = collect($items)->filter(function ($item, $k) {
                return $item->type != 'deck';
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

        return response()->json($data, $this->success);
    }

    public function fullPaid(Request $request): JsonResponse
    {
        $data = ['success' => false, 'message' => __('Sorry! something went wrong')];
        //validation rules
        $validator = Validator::make($request->all(), [
            'booking_id' => 'bail|required|numeric|exists:bookings,id',
            'payment_method' => 'bail|required|in:cash,bkash,rocket,nagad',
            'transaction_id' => 'bail|nullable|string',
            'paid_amount' => 'bail|required|numeric'
        ]);
        if ($validator->fails()) {
            $data['message'] = $validator->errors()->first();
        } else {
            try {
                DB::transaction(function () use (&$data, $request) {
                    $booking = Booking::findOrFail($request->booking_id);
                    $dues = round($booking->total_payable - $booking->payment->paid_amount, 2);
                    DB::table('payment_collectors')->insert([
                        'booking_id' => $booking->id,
                        'payment_id' => $booking->payment->id,
                        'supervisor_id' => Auth::user()->id,
                        'payment_type' => $request->payment_method,
                        'remarks' => (round($dues) == round($request->paid_amount)) ? 'Full paid' : 'Partial payment',
                        'amount' => round($request->paid_amount, 2)
                    ]);
                    if(round($dues) == round($request->paid_amount)) {
                        $booking->payment->paid_amount =  round($booking->total_payable, 2);
                        $booking->payment->dues = 0;
                    } else {
                        $booking->payment->paid_amount = round($booking->payment->amount, 2) + round($request->paid_amount, 2);
                        $booking->payment->dues = round($booking->payment->dues, 2) - round($request->paid_amount, 2);
                        $booking->payment->store_amount = $booking->payment->paid_amount;
                    }
                    $booking->payment->save();
                    if(round($booking->total_payable) === round($booking->payment->paid_amount)) {
                        $booking->status = 'COMPLETE';
                        $booking->save();
                    }
                    $data['success'] = true;
                    $data['message'] = __('Payment complete');
                });
            } catch (\Exception $e) {
                $data['message'] = $e->getMessage();
            }
        }
        return response()->json($data, $this->success);
    }

    public function quickPrint(Request $request)
    {
        $data = ['success' => false, 'message' => __('Ticket cannot print, please try again.')];
        //validation rules
        $validator = Validator::make($request->all(), [
            'deck_id' => 'bail|required|numeric|exists:deck_fares,id',
            'trip_id' => 'bail|required|numeric|exists:vehicle_schedules,id',
            'payment_method' =>  'bail|nullable|string'
        ]);

        //validation fails
        if ($validator->fails()) {
            $data['message'] = $validator->errors()->first();
        } else {
            $item = DeckFare::find($request->deck_id);
            $schedule = VehicleSchedule::find($request->trip_id);
            $leavingTime = strtotime($schedule->leaving_at) - (3*60*60);
            try {
                if($leavingTime < (time()) && ($leavingTime + ($schedule->operation_hour * 60 * 60)) >= time()) {
                    DB::Transaction(function () use ($request, $item, $schedule, &$data) {
                        $user = Auth::user();
                        $discounted = 0;
                        $fare = ($schedule->schedule_type == 'reverse') ? $item->reverse_fare : $item->fare;
                        $vat_applicable_to = $schedule->launch['merchant']['vat_applicable_to'];
                        $vat_amount = abs(getOption('vat_amount', 0));
                        $vat_total = ($fare * ($vat_amount / 100));
                        $charge_amount = abs(getOption('service_charge_counter', 0));
                        $charge_total = ($fare * ($charge_amount / 100));
                        if ($schedule->discounts) {
                            if ($user->type == 'admin') {
                                $userType = AppConst::OWNER;
                            } else {
                                $userType = 'merchant';
                            }
                            foreach ($schedule->discounts as $discount) {
                                $calculated = ($discount->type == 'p') ? (($fare * $request->passengers) * ($discount->amount / 100)) : $discount->amount;
                                if (($discount->is_deck) && (($userType == $discount->applicable_to) || $discount->applicable_to == 'both')) {
                                    $discounted += $calculated;
                                }
                            }
                        }

                        $booking = Booking::create([
                            'booking_date' => date('Y-m-d'),
                            'customer_id' => $user->id,
                            'user_id' => $user->id,
                            'total_amount' => $fare,
                            'total_discount' => $discounted,
                            'vat_amount' => $vat_amount,
                            'charge_amount' => $charge_amount,
                            'total_payable' => $fare + $vat_total + $charge_total - $discounted,
                            'vat_total' => ($vat_applicable_to == 'customer') ? $vat_total : 0,
                            'charge_total' => ($user->type != 'merchant') ? $charge_total : 0,
                            'booking_party' => ($user->type == 'merchant') ? 'merchant' : AppConst::OWNER,
                            'status' => 'COMPLETE',
                            'platform' => 'android',
                            'payment_status' => 1
                        ]);

                        $passenger = new \stdClass();
                        $passenger->name = $user->name;
                        $passenger->mobile = $user->mobile;
                        $passenger->person = 1;
                        $incentive = 0;
                        $incentive_type = 'percent';
                        if ($user->hasRole('supervisor')) {
                            $mapping = collect($user->supervisorMappings)->where('vehicle_id', $item->vehicle_id)->first();
                            $incentive = $mapping->supervisor_incentive;
                            $incentive_type = $mapping->incentive_type;
                        }

                        $bookingItem = BookingItem::create([
                            'booking_id' => $booking->id,
                            'vehicle_id' => $item->vehicle_id,
                            'customer_id' => $booking->customer_id,
                            'booking_type' => 'deck',
                            'deck_fare_id' => $item->id,
                            'price' => abs($fare),
                            'trip_id' => $schedule->id,
                            'trip_date' => $schedule->schedule_date,
                            'booking_date' => $booking->booking_date,
                            'discount' => $discounted,
                            'boarding_point' => ($schedule->schedule_type == 'reverse') ? json_encode(['name' => $item->departureTo['ghat']['name'], 'id' => $item->departureTo['ghat']['id']]) : json_encode(['name' => $item->departureFrom['ghat']['name'], 'id' => $item->departureFrom['ghat']['id']]),
                            'passenger' => json_encode($passenger),
                            'route_name' => ($schedule->schedule_type == 'reverse') ? $item->departureTo->ghat->name . ' - ' . $item->departureFrom->ghat->name : $item->departureFrom->ghat->name . ' - ' . $item->departureTo->ghat->name,
                            'vat_amount' => $vat_amount,
                            'charge_amount' => $charge_amount,
                            'vat_applicable_to' => $vat_applicable_to,
                            'discount_type' => 'discount',
                            'is_honorium' => (int)0,
                            'honorium_charge' => 0,
                            'honorium_type' => 'percent',
                            'booking_party' => $booking->booking_party,
                            'status' => 1,
                            'incentive' => $incentive,
                            'incentive_type' => $incentive_type,
                            'printed' => 1
                        ]);

                        if ($booking->save()) {
                            $payment = Payment::firstOrnew([
                                'booking_id' => $booking->id
                            ]);
                            $payment->booking_id = $booking->id;
                            $payment->payment_method = ($request->payment_method) ? strtolower($request->payment_method) : 'cash';
                            $payment->transaction_id = uniqid($booking->id . '_', false);
                            $payment->customer_id = $booking->customer_id;
                            $payment->bank_tran_id = ($request->transaction_id) ? $request->transaction_id : '';
                            $payment->status = 'success';
                            $payment->paid_amount = $booking->total_payable;
                            $payment->store_amount = $booking->total_payable;
                            $payment->dues = 0;
                            $payment->save();

                            $collection = PaymentCollector::create([
                                'booking_id' => $booking->id,
                                'payment_id' => $payment->id,
                                'supervisor_id' => $booking->user_id,
                                'payment_type' => $payment->payment_method,
                                'remarks' => 'Quick deck print',
                                'amount' => $booking->total_payable
                            ]);

                            $qrCode = \QrCode::size(500)
                                ->format('png')
                                // ->color(33, 152, 118)
                                ->size(500)
                                ->merge(public_path('default/logo-icon.png'), .1, true)
                                ->generate((string)$booking->id, public_path('qrs/' . $booking->id . '.png'));
                            $data['success'] = true;
                            $data['order_id'] = $booking->id;
                            $data['ticket_id'] = $bookingItem->id;
                            $data['trans_id'] = $payment->transaction_id;
                            $data['message'] = __('Ticket booked');
                        }
                    }, 2);
                } else {
                    $message = __('Booking operation time is over');
                    if($leavingTime > time()) {
                        $message = __('Booking operation will start from ') . date('d/m/Y h:i a', $leavingTime);
                    }
                    throw new \Exception($message);
                }
            } catch (\Exception $e) {
                $data['message'] = $e->getMessage();
            }
        }

        return response()->json($data, $this->success);
    }

    public function details(Request $request, $id)
    {
        $booking = Booking::with(['bookingItems.trip.route', 'cancellations', 'bookingItems.item.cabinType', 'bookingItems.trip.launch', 'payment'])
            ->orderBy('booking_date', 'desc')->findOrFail($id);

        $responseArr = [];
        if ($booking) {
            $dues = round(($booking->total_payable - $booking->payment->paid_amount), 2);
            $responseArr['id'] = $booking->id;
            $responseArr['pnr'] = $booking->id;
            $responseArr['qr'] = asset('qrs/' . $booking->id . '.png');
            $responseArr['booking_date'] = date('Y-m-d H:i:s', strtotime($booking->created_at));
            $responseArr['booking_date_formated'] = date('d M, Y h:i A', strtotime($booking->created_at));
            $responseArr['payment_status'] = $booking->payment['status'];
            $responseArr['total_amount'] = $booking->total_amount;
            $responseArr['total_discount'] = $booking->total_discount;
            $responseArr['vat_amount'] = $booking->vat_amount;
            $responseArr['vat_total'] = $booking->vat_total;
            $responseArr['charge_amount'] = $booking->charge_amount;
            $responseArr['charge_total'] = $booking->charge_total;
            $responseArr['total_payable'] = abs(($booking->total_amount + $booking->vat_total + $booking->charge_total - $booking->total_discount));
            $responseArr['payment'] = $booking->payment;
            $responseArr['dues'] = ($dues <= 0) ? 0 : $dues;
            $responseArr['transaction_id'] = $booking->payment['transaction_id'];
            $responseArr['cancellable'] = false;
            $responseArr['status'] = $booking->status;
            $responseArr['items'] = [];

            $cancellations = [];
            if ($booking->cancellations) {
                foreach ($booking->cancellations as $cancellation) {
                    $cancellations = array_merge_recursive($cancellations, explode(',', $cancellation->items));
                }
            }

            // $responseArr['status'] = $booking->status;

            foreach ($booking->bookingItems as $item) {
                $row = [
                    'id' => $item['id'],
                    'booking_id' => $item['booking_id'],
                    'cabin_no' => ($item['item']) ? $item['item']['cabinType']['letter'] . '-' . $item['item']['cabin_no'] : '',
                    'cabin_type' => $item['booking_type'],
                    'fare' => $item['price'],
                    'discount' => $item['discount'],
                    'is_ac' => $item['item']['cabinType']['is_ac'],
                    'vehicle_name' => $item['trip']['launch']['name'],
                    'route_name' => $item['trip']['route']['route_name'],
                    'schedule_date' => date('d F Y', strtotime($item['trip_date'])),
                    'leaving_time' => $item['trip']['leaving_at'],
                    'leaving_time_formated' => date('h:i A', strtotime($item['trip']['leaving_at'])),
                    'boarding_point' => json_decode($item['boarding_point']),
                    'passenger' => json_decode($item['passenger']),
                    'from' => ($item['trip']['schedule_type'] == 'reverse') ? $item['trip']['endingPoint']['ghat']['name'] : $item['trip']['startingPoint']['ghat']['name'],
                    'to' => ($item['trip']['schedule_type'] == 'reverse') ? $item['trip']['startingPoint']['ghat']['name'] : $item['trip']['endingPoint']['ghat']['name'],
                    'cancellable' => ($item['trip_date'] >= date('Y-m-d')) ? ((in_array($item['id'], $cancellations)) ? false : true) : false,
                    'status' => $item['status']
                ];
                if ($item['trip']['schedule_type'] == 'reverse') {
                    $irow['route_name'] = $item['trip']['endingPoint']['ghat']['name'] . ' - ' . $item['trip']['startingPoint']['ghat']['name'];
                }
                if ($item['status'] == 1 && $item['trip_date'] >= date('Y-m-d')) {
                    $responseArr['cancellable'] = true;
                }
                array_push($responseArr['items'], $row);
            }

            if (!getOption('is_cancellation_enabled')) {
                $responseArr['cancellable'] = false;
            }
        }

        return response()->json(['success' => true, 'booking' => $responseArr], $this->success);
    }
}
