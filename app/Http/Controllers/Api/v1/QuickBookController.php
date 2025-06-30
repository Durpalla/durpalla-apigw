<?php

namespace App\Http\Controllers\Api\v1;

use App\Constants\AppConst;
use App\Events\UserCreated;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\Cabin;
use App\Models\CabinLock;
use App\Models\CabinType;
use App\Models\DeckFare;
use App\Models\Payment;
use App\Models\PaymentCollector;
use App\Models\ScanLog;
use App\Models\TicketPrint;
use App\Models\User;
use App\Models\VehicleRoute;
use App\Models\VehicleSchedule;
use App\Services\CalculationService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class QuickBookController extends Controller
{
    private $calculation;
    public function __construct( CalculationService $calculationService)
    {
        $this->calculation = $calculationService;
        $this->status = 200;
        $this->success = 200;
        $this->middleware('auth:api');
    }

    public function findBookings(Request $request)
    {
        $data = ['success' => false, 'bookings' => [], 'message' => 'Nothing found'];
        $validator = Validator::make($request->all(), [
            'props' => 'required|string'
        ]);

        if ($validator->fails() == True) {
            $data['message'] = $validator->errors()->first();
        } else {
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
                                'launch_name' => $item['trip']['launch']['name'],
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
                            'items' => $items
                        ]);
                    }
                }

                $data['bookings'] = ($responseArr) ? $responseArr : null;
                $data['ticket'] = ($ticket) ? $ticket : null;
                $data['message'] = 'Booking found';
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
                                'launch_name' => $item['trip']['launch']['name'],
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
                                'launch_name' => $item['trip']['launch']['name'],
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
                    $data['message'] = 'Booking found';
                    $data['success'] = true;
                    $data['ticket'] = ($ticket) ? $ticket : null;
                } else {
                    $data['message'] = 'Nothing found';
                    $data['booking'] = null;
                    $data['ticket'] = null;
                }
            } else {
                $data['message'] = 'Nothing found';
                $data['booking'] = null;
                $data['ticket'] = null;
            }
        }

        return response()->json($data, $this->success);
    }

    public function getBookingByID(Request $request, int $booking_id)
    {
        $data = ['success' => true, 'booking' => ['items' => []], 'message' => 'Booking not found'];

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
                    'launch_id' => $bookingItem->vehicle_id,
                    'launch_name' => $bookingItem->launch->name,
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
        $data = ['success' => false, 'message' => 'Cannot handle request'];
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
                $data['message'] = 'All items printed';
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
                    $data['message'] = 'Ticket print confirmed';
                }
            } else {
                $data['message'] = 'Ticket not printable';
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
                $data['message'] = 'An OTP sent to customers mobile';
            } else {
                $data['message'] = 'Ticket not printable';
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
                $data['message'] = 'Reprint confirmed';
            } else {
                $data['message'] = 'Your otp code is not valid';
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

        $returnArray = [];

        if ($results) {
            $startTime = date('Y-m-d H:i:s', strtotime('-24 hour'));
            $endTime = date('Y-m-d H:i:s', strtotime('+24 hour'));
            $currentTime = time();
            foreach ($results as $result) {
                $validity = 'invalid';
                if(strtotime($result->leaving_at) <= $currentTime && (strtotime($result->leaving_at) + ($result->operation_hour * 60*60)) >= $currentTime) {
                    $validity = 'valid';
                }
//                if ($result->leaving_at >= $startTime && $result->leaving_at <= $endTime) {
                    $row['trip_id'] = $result->id;
                    $row['route_id'] = $result->route_id;
                    $row['route_name'] = $result->route['route_name'];
                    $routeArr = explode('-', $result->route['route_name']);
                    if ($result->schedule_type == 'reverse' && (count($routeArr) > 1)) {
                        $row['route_name'] = $routeArr[1] . '-' . $routeArr[0];
                    }
                    $row['launch_id'] = $result->vehicle_id;
                    $row['launch_name'] = $result->launch['name'];
                    $row['launch_photo'] = $result->launch['photo'];
                    $row['schedule_date'] = $result->schedule_date;
                    $row['schedule_type'] = $result->schedule_type;
                    $row['leaving_at'] = date('Y-m-d H:i:s', strtotime($result->leaving_at));
                    $row['leaving_time'] = date('h:i A', strtotime($result->leaving_at));
                    $row['operation_hour'] = round($result->operation_hour, 2);
                    $row['operation_end_at'] = date('Y-m-d H:i:s', strtotime($result->operation_timeline));
                    $row['total_cabins'] = (int)$result->cabinMappings->count();
                    $row['cabin_available'] = 0;
                    $row['total_seats'] = (int)$result->seatMappings->count();
                    $row['seat_available'] = 0;
                    $row['total_tickets'] = $result->launch['passengers_capacity'];
                    $row['ticket_available'] = $row['total_tickets'] - abs($result->ticketBookings()->count());
                    $row['starting_point'] = $result->startingPoint['ghat']['name'];
                    $row['ending_point'] = $result->endingPoint['ghat']['name'];
                    $row['validity'] = $validity;
                    $row['stoppages'] = [];

                    array_push($row['stoppages'], [
                        'id' => $result->startingPoint['id'],
                        'name' => $result->startingPoint['ghat']['name'],
                        'type' => $result->startingPoint['type']
                    ]);
                    foreach ($result->boardingVias as $stoppage) {
                        $prop['id'] = $stoppage['id'];
                        $prop['name'] = $stoppage['ghat']['name'];
                        $prop['type'] = $stoppage['type'];
                        array_push($row['stoppages'], $prop);
                    }
                    array_push($row['stoppages'], [
                        'id' => $result->endingPoint['id'],
                        'name' => $result->endingPoint['ghat']['name'],
                        'type' => $result->endingPoint['type']
                    ]);

                    if ($result->schedule_type == 'reverse') {
                        krsort($row['stoppages']);
                        $row['stoppages'] = array_values($row['stoppages']);
                    }

                    $books = [];
                    if ($result->bookingItems) {
                        foreach ($result->bookingItems as $item) {
                            array_push($books, $item['cabin_id']);
                        }
                    }

                    $locks = [];
                    if ($result->locks) {
                        foreach ($result->locks as $lock) {
                            array_push($locks, $lock['cabin_id']);
                        }
                    }

                    if ($result->cabinMappings) {
                        foreach ($result->cabinMappings as $cabin) {
                            if (!in_array($cabin['cabin_id'], $books) && !in_array($cabin['cabin_id'], $locks) && ($cabin['ownership'] == 'merchant' || $cabin['honorium'] == 1) && !($cabin['is_reserved'])) {
                                $row['cabin_available'] += 1;
                            }
                        }
                    }

                    if ($result->seatMappings) {
                        // dd( $result->seatMappings );
                        foreach ($result->seatMappings as $seat) {
                            if (!in_array($seat['cabin_id'], $books) && !in_array($seat['cabin_id'], $locks) && ($seat['ownership'] == 'merchant' || $seat['honorium'] == 1) && !($seat['is_reserved'])) {
                                $row['seat_available'] += 1;
                            }
                        }
                    }

                    array_push($returnArray, $row);
//                }
            }
        }

        return response()->json(['success' => true, 'data' => $returnArray], $this->success);
    }

    /**
     * Display a tip details
     * Parameter is trip id
     * @return \Illuminate\Http\Response
     */
    public function trip(Request $request, $id)
    {
        $trip = VehicleSchedule::with(['bookingItems' => function ($q) use ($id) {
            $q->where('trip_id', $id);
        }, 'locks' => function ($q) use ($id) {
            $q->where('trip_id', $id);
        }, 'discounts' => function ($q) {
            $q->where('is_deck', 1);
        },
            'launch.merchant', 'route.startingPoint.ghat', 'route.endingPoint.ghat', 'route.boardingVias.ghat', 'mappings'])->findOrFail($id);

        $returnArray = [
            'id' => $trip->id,
            'launch_id' => $trip->vehicle_id,
            'merchant_id' => $trip->launch['merchant_id'],
            'route_id' => $trip->route_id,
            'launch_name' => $trip->launch['name'],
            'launch_route' => $trip->route['route_name'],
            'schedule_date' => date('Y-m-d H:i:s', strtotime($trip->leaving_at)),
            'scheduled_date' => date('D M, Y', strtotime($trip->leaving_at)),
            'date' => date('d', strtotime($trip->schedule_date)),
            'month' => date('M', strtotime($trip->schedule_date)),
            'cabin_rows' => 3,
            'rowClass' => 'col-sm-4 col-xs-4',
            'cabins' => [],
            'seats' => [],
            'decks' => [],
            'cabin_types' => [],
            'seat_types' => [],
            'stoppages' => [],
            'vat_amount' => getOption('vat_amount', 0),
            'vat_applicable_to' => $trip['launch']['merchant']['vat_applicable_to']
        ];

        $floor = ( int )($request->floor) ? $request->floor : 1;

        $query = Cabin::with(['cabinType'])->where(['vehicle_id' => $trip->vehicle_id, 'floor' => $floor]);

        $cabins = $query->get();

        $tripMappings = [];
        if ($trip->mappings) {
            foreach ($trip->mappings as $mapping) {
                array_push($tripMappings, $mapping->cabin_id);
            }
        }

        $books = [];
        if ($trip->bookingItems) {
            foreach ($trip->bookingItems as $item) {
                array_push($books, $item['cabin_id']);
            }
        }


        $locks = [];
        if ($trip->locks) {
            foreach ($trip->locks as $lock) {
                array_push($locks, $lock['cabin_id']);
            }
        }
        // return response()->json($locks);

        $mappings = new Collection($trip->mappings);

        if ($cabins) {
            foreach ($cabins as $cabin) {
                $row['trip_id'] = $trip->id;
                $row['trip_date'] = date('Y-m-d H:i:s', strtotime($trip->leaving_at));
                $row['route_id'] = $trip->route_id;
                $row['launch_id'] = $cabin['vehicle_id'];
                $row['launch_name'] = $trip->launch['name'];
                $row['merchant_id'] = $trip->launch['merchant_id'];
                $row['cabin_id'] = $cabin['id'];
                $row['cabin_type_id'] = $cabin['type_id'];
                $row['cabin_type'] = $cabin['type'];
                $row['cabin_type_name'] = ($cabin['cabinType']) ? $cabin['cabinType']['letter'] : null;
                $row['cabin_floor'] = $cabin['floor'];
                $row['cabin_no'] = ($cabin['type'] == 'cabin') ? $cabin['cabinType']['letter'] . '-' . $cabin['cabin_no'] : $cabin['cabin_no'];
                $row['fare'] = $cabin['fare'];
                $row['cabin_is_ac'] = $cabin['cabinType']['is_ac'];
                $row['capacity'] = $cabin['passenger_capacity'];
                $row['cabin_row'] = $cabin['cabin_row'];
                $row['cabin_position'] = $cabin['cabin_position'];
                $row['description'] = ($cabin->type != 'deck') ? $cabin['cabinType']['name'] . ' - ' . $cabin['cabinType']['letter'] . '-' . $cabin['cabin_no'] : '1 Deck ticket';
                $row['status'] = 0;
                $row['cabin_class'] = 'cabin-disable';
                if (in_array($cabin['id'], $tripMappings)) {
                    $mapping = $mappings->where('cabin_id', $cabin['id'])->first();
                    if (($mapping->ownership == 'merchant' || $mapping->honorium == 1) && !($mapping->is_reserved) && !in_array($mapping->cabin_id, $books) && !in_array($mapping->cabin_id, $locks)) {
                        $row['status'] = 1;
                        $row['cabin_class'] = 'cabin-active';
                    }
                    // if( $request->cabin_type > 0 ) {
                    //     if($row['cabin_type'] == 'cabin' && $row['cabin_type_id'] != $request->cabin_type ) {
                    //         $row['cabin_class'] = 'cabin-disable';
                    //     }
                    // }
                    // if( $request->seat_type > 0 ) {
                    //     if($row['cabin_type'] == 'seat' && $row['cabin_type_id'] != $request->seat_type ) {
                    //         $row['cabin_class'] = 'cabin-disable';
                    //     }
                    // }

                    if ($cabin['type'] == 'cabin') {
                        array_push($returnArray['cabins'], $row);
                        $returnArray['cabin_types'][$cabin['type_id']] = $cabin['cabinType']['name'];
                    } elseif ($cabin['type'] == 'seat') {
                        array_push($returnArray['seats'], $row);
                        $returnArray['seat_types'][$cabin['type_id']] = $cabin['cabinType']['name'];
                    }
                }
            }
        }

        //fetch deck fares
        $deckFares = new Collection(DeckFare::with(['departureFrom.ghat', 'departureTo.ghat'])->where('route_id', $trip->route_id)->get());
        $launchDefined = $deckFares->where('vehicle_id', $trip->vehicle_id);

        if ($launchDefined->count()) {
            foreach ($launchDefined as $deckfare) {
//                dd($deckfare);
                $deck['id'] = $deckfare['id'];
                $deck['from'] = ($trip->schedule_type == 'reverse') ? $deckfare['departureTo']['ghat']['name'] : $deckfare['departureFrom']['ghat']['name'];
                $deck['to'] = ($trip->schedule_type == 'reverse') ? $deckfare['departureFrom']['ghat']['name'] : $deckfare['departureTo']['ghat']['name'];
                $deck['fare'] = ($trip->schedule_type == 'reverse') ? $deckfare['reverse_fare'] : $deckfare['fare'];
                $discounted = 0;
                if ($trip->discounts) {
                    foreach ($trip->discounts as $discount) {
                        $discounted += ($discount->type == 'percent') ? ($deck['fare'] * ($discount->amount / 100)) : $discount->amount;
                    }
                }

                $vat = 0;
                if ($trip->launch['merchant']['vat_applicable_to'] == 'customer') {
                    $vat_amount = getOption('vat_amount', 0);
                    $vat += $deck['fare'] * ($vat_amount / 100);
                }
                $finalFare = $deck['fare'] + $vat - $discounted;
                $deck['fare'] = $finalFare;
                $deck['discount_percent'] = (($deck['fare'] - $finalFare) / 100) * $deck['fare'];

                array_push($returnArray['decks'], $deck);
            }
        } else {
            foreach ($deckFares as $deckfare) {
                if ($deckfare->vehicle_id == '') {
                    $deck['id'] = $deckfare['id'];
                    $deck['from'] = ($trip->schedule_type == 'reverse') ? $deckfare['departureTo']['ghat']['name'] : $deckfare['departureFrom']['ghat']['name'];
                    $deck['to'] = ($trip->schedule_type == 'reverse') ? $deckfare['departureTo']['ghat']['name'] : $deckfare['departureTo']['ghat']['name'];
                    $deck['fare'] = ($trip->schedule_type == 'reverse') ? $deckfare['reverse_fare'] : $deckfare['fare'];
                    array_push($returnArray['decks'], $deck);
                }
            }
        }

        //push stoppages
        array_push($returnArray['stoppages'], [
            'id' => $trip->route['startingPoint']['id'],
            'name' => $trip->route['startingPoint']['ghat']['name'],
            'type' => $trip->route['startingPoint']['type']
        ]);
        if ($trip->route['boardingVias']) {
            foreach ($trip->route['boardingVias'] as $stoppage) {
                array_push($returnArray['stoppages'], ['id' => $stoppage['id'], 'name' => $stoppage['ghat']['name']]);
            }
        }
        array_push($returnArray['stoppages'], [
            'id' => $trip->route['endingPoint']['id'],
            'name' => $trip->route['endingPoint']['ghat']['name'],
            'type' => $trip->route['endingPoint']['type']
        ]);

        $cabins = ($returnArray['cabins']) ? _my_group_by($returnArray['cabins'], 'cabin_row') : null;
        $seats = ($returnArray['seats']) ? _my_group_by($returnArray['seats'], 'cabin_row') : null;
        $returnArray['cabins'] = _my_layout($cabins);
        $returnArray['seats'] = _my_layout($seats);

        if ($returnArray['cabins']) {
            ksort($returnArray['cabins']);
        }
        if ($returnArray['seats']) {
            ksort($returnArray['seats']);
        }
        if ($returnArray['cabin_types']) {
            $types = [];
            foreach ($returnArray['cabin_types'] as $key => $type) {
                array_push($types, ['id' => $key, 'name' => $type]);
            }
            $returnArray['cabin_types'] = $types;
        }
        if ($returnArray['seat_types']) {
            $types = [];
            foreach ($returnArray['seat_types'] as $key => $type) {
                array_push($types, ['id' => $key, 'name' => $type]);
            }
            $returnArray['seat_types'] = $types;
        }
        return response()->json(['success' => true, 'data' => $returnArray], $this->success);
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
        $data = ['success' => false, 'message' => 'Your item cannot be locked'];
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
                $data['message'] = 'Your ' . $item->type . ' is already booked';
            } elseif ($item->locks && $item->locks->count() > 0) {
                $data['message'] = 'Your ' . $item->type . ' is already been locked';
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
                    $data['message'] = 'The item has been successfully locked';
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
                        'launch_id' => $item->vehicle_id,
                        'merchant_id' => $item->launch['merchant_id'],
                        'route_id' => $schedule->route_id,
                        'launch_name' => $item->launch['name'],
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
                    $data['message'] = 'Something happened wrong. please try again later.';
                }
            }
        } else {
            $data['message'] = 'Booking operation hour expired';
        }
        return response()->json($data, $this->success);
    }

    public function addToCartDeck(Request $request)
    {
        $data = ['success' => false, 'message' => 'Your item cannot be locked'];
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
                    'launch_id' => $schedule->vehicle_id,
                    'merchant_id' => $schedule->launch['merchant_id'],
                    'route_id' => $schedule->route_id,
                    'launch_name' => $schedule->launch['name'],
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
                $data['message'] = 'Your deck ticket has been added successfully';
            }
        } else {
            $data['message'] = 'Booking operation hour expired';
        }
        return response()->json($data, $this->success);
    }

    /**
     * Confirm order.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function confirm(Request $request)
    {
        $data = ['success' => false, 'message' => 'Your order cannot be confirmed'];
        $user = Auth::user();
        //validation rules
        $rules = [
            'items' => 'bail|required|string',
            'payment_method' => 'bail|required|in:cash,bkash,rocket,nagad,Cash,Bkash,Rocket,Nagad',
            'transaction_id' => 'bail|nullable|string',
            'customer_name' => 'bail|required|string',
            'customer_mobile' => 'bail|required|max:14|regex:/^(01){1}[3456789]{1}(\d){8}$/|min:11',
            'paid_amount' => 'bail|required|numeric'
        ];
        $validator = Validator::make($request->all(), $rules);
        $vatVisibility = 0;
        //validation fails
        if ($validator->fails())
            return response()->json(['success' => false, 'message' => $validator->errors()->first()], $this->success);

        $items = json_decode(str_replace("\\", "", $request->items));
        $launchName = '';
        $trip_date = '';
        $route_name = '';
        $leaving_time = '';
        $boarding_point = '';
        if (is_array($items)) {
            DB::beginTransaction();
            try {
                $customer = User::firstOrnew(['mobile' => $request->customer_mobile]);
                $newCustomer = 0;
                if (!$customer->id) {
                    $customer->mobile = $request->customer_mobile;
                    $customer->name = $request->customer_name;
                    $customer->password = Hash::make(Str::random(8));
                    $newCustomer = 1;
                }
                $customer->save();
                if ($newCustomer) {
                    $role = Role::where('name', 'customer')->first();
                    $customer->assignRole($role);
                    event(new UserCreated($customer, 'office'));
                }
                $booking_items = [];
                $vat_amount = abs(getOption('vat_amount', 0));
                $charge_amount = abs(getOption('service_charge_counter', 0));

                $booking = Booking::create([
                    'booking_date' => date('Y-m-d'),
                    'customer_id' => $customer->id,
                    'user_id' => Auth::user()->id,
                    'total_amount' => 0,
                    'total_discount' => 0,
                    'vat_amount' => $vat_amount,
                    'charge_amount' => $charge_amount,
                    'total_payable' => 0,
                    'vat_total' => 0,
                    'charge_total' => 0,
                    'booking_party' => (Auth::user()->type == 'merchant') ? 'merchant' : AppConst::OWNER,
                    'status' => 'COMPLETE'
                ]);

                // DB::rollback();
                // return response()->json($booking);
                $discount = 0;
                $cabins = [];
                $item_list = ['cabin' => [], 'seat' => []];
                $item_count = collect($items)->count();
                foreach ($items as $item) {
                    $item->type = $item->cabin_type;
                    $deck = ($item->type == 'deck' && $item->cabin_id != null) ? DeckFare::find($item->cabin_id) : null;
                    $cabinItem = ($item->type != 'deck') ? Cabin::find($item->cabin_id) : null;
                    if( $cabinItem && ($cabinItem->launch['merchant']['vat_visibility'] == 1)) {
                        $vatVisibility = 1;
                    } elseif($deck && ($deck->launch['merchant']['vat_visibility'] == 1)) {
                        $vatVisibility = 1;
                    }
                    $item_type = CabinType::find($item->cabin_type_id);
                    $item_list[$item->cabin_type][] = [
                        'type' => ucfirst($item_type->name),
                        'cabin_no' => preg_replace("/[^0-9.]/", "", $item->cabin_no),
                        'is_ac' => ($item->cabin_is_ac) ? 'AC' : 'Non AC'
                    ];
                    array_push($cabins, ucfirst($item->cabin_type) . ': ' . $item->description);
                    $discount += abs($item->discount);
                    $trip = VehicleSchedule::with(['launch.merchant', 'startingPoint.ghat', 'endingPoint.ghat'])->find($item->trip_id);
                    $launchName = $trip->launch['name'];
                    $route_name = ($trip->schedule_type == 'reverse') ? $trip->endingPoint->ghat['name'] . ' - ' . $trip->startingPoint->ghat['name'] : $trip->startingPoint->ghat['name'] . ' - ' . $trip->endingPoint->ghat['name'];
                    if($item->type == 'deck' && $deck) {
                        $route_name = ($trip->schedule_type == 'reverse') ? $deck->departureTo->ghat->name . ' - ' . $deck->departureFrom->ghat->name : $deck->departureFrom->ghat->name . ' - ' . $deck->departureTo->ghat->name;
                    }
                    $trip_date = date('Y-m-d', strtotime($trip->schedule_date));
                    $leaving_time = date('Y-m-d H:i:s', strtotime($trip->leaving_at));
                    $boarding_point = (isset($item->boardingPoint)) ? $item->boardingPoint : null;
                    $item->vat_applicable_to = $trip->launch['merchant']->vat_applicable_to;

                    if ($item->vat_applicable_to == 'customer') {
                        $booking->vat_total += abs($item->fare * ($vat_amount / 100));
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
                        'vehicle_id' => $item->launch_id,
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
                        'discount_type' => 'discount',
                        'is_honorium' => (int)$item->is_honorium,
                        'honorium_charge' => abs($item->honorium_charge),
                        'honorium_type' => $trip->launch['merchant']['honorium_type'],
                        'booking_party' => $booking->booking_party,
                        'status' => 1,
                        'incentive' => $item->incentive,
                        'incentive_type' => $item->incentive_type,
                        'route_name' => $route_name,
                        'deck_fare_id' => $item->cabin_id
                    ]);

                    if ($item->type != 'deck') {
                        CabinLock::where([
                            'cabin_id' => $item->cabin_id,
                            'trip_id' => $item->trip_id
                        ])->delete();
                    }
                }

                // DB::rollback();
                // return ( $booking_items );

                //save items
                BookingItem::insert($booking_items);

                //update order with total amount
                $booking->platform = 'android';
                $booking->total_amount = abs($booking->total_amount);
                if (Auth::user()->type != 'merchant') {
                    $booking->charge_total = abs($booking->total_amount * ($charge_amount / 100));
                }
                $booking->total_payable = abs(($booking->total_amount + $booking->vat_total + $booking->charge_total - $booking->total_discount));
//                $booking->dues = round($booking->total_payable, 2) - round($request->paid_amount, 2);
                //set payment record
                $payment = Payment::firstOrnew([
                    'booking_id' => $booking->id
                ]);
                $payment->booking_id = $booking->id;
                $payment->payment_method = strtolower($request->payment_method);
                $payment->paid_amount = $request->paid_amount;
                $payment->dues = round($booking->total_payable, 2) - round($request->paid_amount, 2);
                $payment->store_amount = round($request->paid_amount, 2);
                $payment->transaction_id = uniqid($booking->id . '_', false);
                $payment->customer_id = $booking->customer_id;
                $payment->bank_tran_id = ($request->transaction_id) ? $request->transaction_id : '';
                $payment->status = 'success';
                $payment->save();
                PaymentCollector::create([
                    'booking_id' => $booking->id,
                    'payment_id' => $payment->id,
                    'supervisor_id' => $user->id,
                    'amount' => $request->paid_amount,
                    'payment_type' => $payment->payment_method,
                    'remarks' => ($payment->total_payable == $payment->paid_amount) ? 'Full payment' : 'Partial payment'
                ]);
                $booking->save();
                DB::commit();
                $qrstring = ($payment->dues > 0) ? $booking->id . '@' . round($payment->dues) : $booking->id;
                $qrCode = \QrCode::size(500)
                    ->format('png')
                    // ->color(33, 152, 118)
                    ->size(500)
                    ->merge(public_path('default/logo-icon.png'), .1, true)
                    ->generate((string) $qrstring, public_path('qrs/' . $booking->id . '.png'));
                if($customer->hasRole('customer')) {
                    $order = Booking::find($booking->id);
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
                }
                $data['success'] = true;
                $data['order_id'] = $booking->id;
                $data['message'] = 'Your order has been confirmed.';
                $data['advance'] = ($booking->total_payable > $payment->paid_amount) ? true : false;
                $data['token'] = [
                    'launch_name' => $launchName,
                    'trip_date' => $trip_date,
                    'route_name' => $route_name,
                    'pnr' => $booking->id,
                    'booking_time' => date('Y-m-d H:i:s', strtotime($booking->created_at)),
                    'leaving_at' => $leaving_time,
                    'transaction_id' => $payment->transaction_id,
                    'booking_items' => "",
                    'items' => $item_list,
                    'items_count' => $item_count,
                    'supervisor_name' => $user->name,
                    'customer_name' => $customer->name,
                    'customer_mobile' => $customer->mobile,
                    'for' => ($user->id == $customer->id) ? 'self' : 'other',
                    'subtotal' => $booking->total_amount,
                    'vat_visibility' => $vatVisibility,
                    'total_vat' => $booking->vat_total,
                    'total_charge' => $booking->charge_total,
                    'total_discount' => $booking->total_discount,
                    'total' => $booking->total_payable,
                    'paid' => $payment->paid_amount,
                    'due' => round($booking->total_payable - $payment->paid_amount, 2),
                    'boarding_point' => ($boarding_point) ? $boarding_point->name : '',
                    'hotline' => getOption('company_hotline_code', '')
                ];
            } catch (\Exception $e) {
                $data['message'] = $e->getMessage();
                DB::rollback();
            }
        }

        return response()->json($data, $this->success);
    }

    public function fullPaid(Request $request)
    {
        $data = ['success' => false, 'message' => 'Sorry! something went wrong'];
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
                    $data['message'] = 'Payment complete';
                });
            } catch (\Exception $e) {
                $data['message'] = $e->getMessage();
            }
        }
        return response()->json($data, $this->success);
    }

    public function quickPrint(Request $request)
    {
        $data = ['success' => false, 'message' => 'Ticket cannot print, please try again.'];
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
                            $data['message'] = 'Ticket booked';
                        }
                    }, 2);
                } else {
                    $message = 'Booking operation time is over';
                    if($leavingTime > time()) {
                        $message = 'Booking operation will start from ' . date('d/m/Y h:i a', $leavingTime);
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
                    'launch_name' => $item['trip']['launch']['name'],
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
