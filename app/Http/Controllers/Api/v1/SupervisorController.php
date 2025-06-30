<?php

namespace App\Http\Controllers\Api\v1;

use App\Constants\AppConst;
use App\Models\Booking;
use App\Models\BookingItem;
use App\Http\Controllers\Controller;
use App\Models\VehicleSchedule;
use App\Models\Vehicle;
use App\Models\ScanLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Services\CalculationService;
use App\Services\SupervisorService;

class SupervisorController extends Controller
{
    private $status;
    private $success;
    private $supervisor;
    private $calculation;

    public function __construct( SupervisorService $supervisorService, CalculationService $calculationService)
    {
        $this->status = 200;
        $this->success = 200;
        $this->supervisor = $supervisorService;
        $this->calculation = $calculationService;
        $this->middleware('auth:api');
    }

    public function wallet(Request $request)
    {
        $user = Auth::user();
        $query = Booking::with(['bookingConfirmed'])->where('user_id', $user->id)->whereIn('status', ['ACTIVE', 'COMPLETE']);
        $month = ($request->month) ? date('Y-m', strtotime($request->month)) : date('Y-m');
        $query->whereBetween('booking_date', [date('Y-m-01', strtotime($month)), date('Y-m-t', strtotime($month))]);
        $bookings = $query->get();

        $wallet = [
            'total_bookings' => 0,
            'total_booking_amount' => 0,
            'month' => date('Y-m-01 H:i:s', strtotime($month)),
            'total_incentives' => 0
        ];

        if ($bookings) {
            foreach ($bookings as $booking) {
                $wallet['total_bookings'] += 1;

                if ($booking->bookingConfirmed) {
                    foreach ($booking->bookingConfirmed as $item) {
//                        dd( $item );
                        if ($item['incentive_type'] == 'percent') {
                            $wallet['total_incentives'] += abs(($item['price'] - $item['discount']) * ($item['incentive'] / 100));
                        } else {
                            $wallet['total_incentives'] += abs($item['incentive']);
                        }
                    }
                }
            }
        }

        return response()->json(['success' => true, 'wallet' => $wallet], $this->success);
    }

    public function bookingHistory(Request $request)
    {
        $query = Booking::with(['payment', 'collections', 'customer'])->where('user_id', Auth::user()->id);
        if ($request->from) {
            $query->where('booking_date', '>=', date('Y-m-d', strtotime($request->from)));
        }
        if ($request->to) {
            $query->where('booking_date', '<=', date('Y-m-d', strtotime($request->to)));
        }
        $bookings = $query->orderByDesc('created_at')->paginate(15);
        $bookings = $bookings->map(function ($item) {
            $item->booking_date = date('Y-m-d H:i:s', strtotime($item->created_at));
            return $item;
        });
        return response()->json(['success' => true, 'bookings' => $bookings], $this->success);
    }

    public function jobs()
    {
        $jobs = $this->supervisor->getJobs();

        return response()->json(['success' => true, 'message' => '', 'data' => $jobs]);
    }

    public function myCart(Request $request)
    {
        $data = ['success' => false, 'message' => 'No booking found', 'data' => []];
        $tripDate = ($request->trip_date) ? date('Y-m-d', strtotime($request->trip_date)) : date('Y-m-d');
        $query = Booking::with(['payment', 'bookingItems.item.cabinType', 'collections', 'customer'])->withCount('collections')
            ->where(['user_id' => Auth::user()->id])
            ->where('total_payable', '>', 0);

        if ($request->pnr) {
            $query->where('id', $request->pnr);
        } else {
            $query->whereHas('bookingItems', function ($q) use ($tripDate) {
                $q->where(['trip_date' => $tripDate, 'status' => 1]);
            });
            if ($request->type == 'complete') {
                $query->whereHas('payment', function ($q) use ($request) {
                    $q->where('dues', 0);
                })->has('collections', '=', 1);
            } elseif ($request->type == 'due') {
                $query->whereHas('payment', function ($q) use ($request) {
                    $q->where('dues', '>', 0);
                });
            } elseif($request->type == 'advance') {
                $query->has('collections', '>', 1);
                $query->whereHas('payment', function($q) {
                    $q->where('dues', '=', 0);
                });
            }
        }
        $bookings = $query->orderByDesc('created_at')->paginate(15);
        if ($bookings) {
            $data['success'] = true;
            $data['message'] = 'Bookings found';
            foreach ($bookings as $booking) {
                $payments = [];
                $collections = [];
                $countCollections = $booking->collections->count();
                $bookingStatus = ($booking->payment['dues'] == 0 && $countCollections > 1) ? 'advance' : (($booking->payment['dues'] == 0 && $countCollections == 1) ? 'complete' : 'due');
                foreach ($booking->collections as $key => $collection) {
                    if($countCollections > 1){
                        $payment_type = ($key == 0) ? 'advance' : 'complete';
                    } else {
                        $payment_type = ($booking->payment['dues'] > 0) ? 'advance' : 'complete';
                    }
                    array_push($payments, [
                        'payment_info' => $payment_type,
                        'payment_method' => $collection['payment_type'],
                        'amount' => $collection['amount']
                    ]);
                }
                $items = ['cabin' => [], 'seat' => [], 'deck' => 0];
                $booking->bookingItems->each(function($item, $key) use(&$items){
                    if($item->booking_type != 'deck') {
                        $items[$item->booking_type][] = (($item->item['cabinType']) ? $item->item['cabinType']['letter'] . '-' : '') . $item->item['cabin_no'];
                    } else {
                        $passenger = json_decode($item->passenger);
                        $items['deck'] += ($passenger) ? $passenger->person : 1;
                    }
                });
                array_push($data['data'], [
                    'booking_id' => $booking->id,
                    'order_id' => $booking->id,
                    'pnr' => $booking->id,
                    'booking_at' => date('Y-m-d H:i:s', strtotime($booking->created_at)),
                    'total_payable' => round($booking->total_payable, 2),
                    'total_paid' => round($booking->payment['paid_amount'], 2),
                    'total_dues' => round($booking->total_payable - $booking->payment['paid_amount'], 2),
                    'payments' => $payments,
                    'items' => $items,
                    'status' => ($booking->status == AppConst::BOOKING_COMPLETE) ? $bookingStatus : 'cancelled'
                ]);
            }
        }

        return response()->json($data, $this->success);
    }

    public function bookingGroupWizePrint(Request $request)
    {
        $data = ['success' => false, 'message' => 'No trip found', 'data' => []];
        $supervisor = Auth::user();
        $tripDate = ($request->trip_date) ? date('Y-m-d', strtotime($request->trip_date)) : date('Y-m-d');
        $launchIds = $supervisor->vehicles->map(function ($item, $key) {
            return $item->vehicle_id;
        });
        $trip = VehicleSchedule::where('schedule_date', $tripDate)->whereIn('vehicle_id', $launchIds)->first();

        if ($trip) {
            $data['message'] = 'Trip found';
            $data['success'] = true;
            $bookings = Booking::with(['payment', 'bookingItems.item.cabinType'])
                ->whereHas('bookingItems', function ($q) use ($trip) {
                    $q->where('trip_id', $trip->id);
                })
                ->where('user_id', Auth::user()->id)
                ->get();

            $responseArr = [
                'info' => [
                    'name' => $supervisor->name,
                    'mobile' => $supervisor->mobile,
                    'launch_name' => $trip->launch->name,
                    'route_name' => ($trip->schedule_type === 'reverse') ? $trip->endingPoint->ghat->name . ' - ' . $trip->startingPoint->ghat->name : $trip->startingPoint->ghat->name . ' - ' . $trip->endingPoint->ghat->name,
                    'leaving_at' => date('Y-m-d H:i:s', strtotime($trip->leaving_at))
                ],
                'complete' => [
                    'total' => 0,
                    'types' => []
                ],
                'due' => [
                    'total' => 0,
                    'types' => []
                ]
            ];
            $completeTypes = [];
            $dueTypes = [];
            if ($bookings) {
                foreach ($bookings as $booking) {
                    if ($booking->bookingItems) {
                        $status = ($booking->payment['dues'] > 0) ? 'due' : 'complete';
                        foreach ($booking->bookingItems as $bookingItem) {
                            if ($bookingItem['status'] === 1) {
                                if ($bookingItem['booking_type'] === 'deck') {
                                    $type = 'Deck - ' . $bookingItem['route_name'] . ' (' . $bookingItem['price'] . ')';
                                } else {
                                    $type = ucfirst($bookingItem['booking_type']) . ' ' . $bookingItem['item']['cabinType']['name'];
                                    $type .= ($bookingItem['item']['cabinType']['is_ac']) ? ' AC' : ' Non AC';
                                }
                                $cabinId = ($bookingItem['booking_type'] == 'deck') ? 1 : (($bookingItem['item']['cabinType']) ? $bookingItem['item']['cabinType']['letter'] . '-' . $bookingItem['item']['cabin_no'] : $bookingItem['item']['cabin_no']);
                                if ($status === 'complete') {
                                    $responseArr['complete']['total'] += 1;
                                    $completeTypes[$type][] = $cabinId;
                                } else {
                                    $responseArr['due']['total'] += 1;
                                    $dueTypes[$type][] = $cabinId;
                                }
                            }
                        }
                    }
                }

                foreach ($completeTypes as $key => $type) {
                    array_push($responseArr['complete']['types'], [
                        'type' => $key,
                        'items' => implode(',', $type),
                        'total' => is_array($type) ? count($type) : 0
                    ]);
                }

                foreach ($dueTypes as $key => $type) {
                    array_push($responseArr['due']['types'], [
                        'type' => $key,
                        'items' => implode(',', $type),
                        'total' => is_array($type) ? count($type) : 0
                    ]);
                }
            }
            $data['data'] = $responseArr;
        }
        return response()->json($data, $this->success);
    }

    public function summaryReport2(Request $request)
    {
        $supervisor = Auth::user();
        $tripDate = ($request->trip_date) ? date('Y-m-d', strtotime($request->trip_date)) : date('Y-m-d');
        $launchIds = $supervisor->vehicles->map(function ($item, $key) {
            return $item->vehicle_id;
        });
        $trip = VehicleSchedule::with('launch')->whereIn('vehicle_id', $launchIds)->where('schedule_date', $tripDate)->first();
        $bookings = BookingItem::with(['booking', 'item.cabinType', 'collectors' => function ($query) use ($supervisor) {
            $query->where('supervisor_id', $supervisor->id);
        }])
            ->select('booking_id', 'cabin_id', 'price', 'booking_type', 'incentive', 'incentive_type', 'discount', 'discount_type', 'vat_amount', 'vat_applicable_to', 'charge_amount')
            ->where(['trip_date' => $tripDate, 'status' => 1])
            ->whereIn('vehicle_id', $launchIds)
            ->whereHas('booking', function ($query) use ($supervisor) {
                $query->where('user_id', $supervisor->id);
            })
//            ->groupBy('booking_type')
//            ->groupBy('price')
            ->get();
        $items = [
            'payments' => [],
            'cabins' => [],
            'seats' => [],
            'decks' => []
        ];
        if ($bookings) {
            foreach ($bookings as $booking) {
                if ($booking->booking_type == 'cabin') {
                    $cabinType = $booking->item['cabinType']['name'];
                    $cabinType .= ($booking->item['is_ac']) ? ' AC' : ' None AC';
                    $items['cabins'][$cabinType][] = $booking;
                } elseif ($booking->booking_type == 'seat') {
                    $seatType = $booking->item['cabinType']['name'];
                    $seatType .= ($booking->item['is_ac']) ? ' AC' : ' None AC';
                    $items['seats'][$seatType][] = $booking;
                } else {
                    $deckType = ($booking->route_name == null) ? $booking->price : $booking->route_name . ' (' . $booking->price . ')';
                    $items['decks'][$deckType][] = $booking;
                }
                $items['payments'][$booking->booking_id] = $booking->collectors;
            }
        }
        $payments = [];
        $summaries = [
            'info' => [
                'name' => $supervisor->name,
                'mobile' => $supervisor->mobile,
                'launch_name' => $trip->launch->name,
                'route_name' => ($trip->schedule_type == 'reverse') ? $trip->endingPoint['ghat']['name'] . ' - ' . $trip->startingPoint['ghat']['name'] : $trip->startingPoint['ghat']['name'] . ' - ' . $trip->endingPoint['ghat']['name'],
                'leaving_at' => date('Y-m-d H:i:s', strtotime($trip->leaving_at))
            ],
            'cabins' => [],
            'seats' => [],
            'decks' => [],
            'payments' => null
        ];
        foreach ($items as $key => $types) {
            if ($key == 'payments' && !empty($types)) {
                foreach ($types as $k => $type) {
                    foreach ($type as $t) {
                        $payments[$t['payment_type']][] = $t;
                    }
                }
            } else {
                foreach ($types as $k => $bookingItems) {
                    $bookingItems = collect($bookingItems);
                    $totalItems = $bookingItems->count();
                    $totalAmount = 0;
                    $totalDues = 0;
                    $totalIncentives = 0;
                    foreach ($bookingItems as $item) {
                        $discount = ($item->discount_type == 'percent') ? ($item['price']) * ($item['discount'] / 100) : $item->discount;
                        $totalAmount += round($item->price - $discount);
                        $totalIncentives = 0;
                        if ($item['incentive_type'] == 'percent') {
                            $totalIncentives += abs(($item['price'] - $discount) * ($item['incentive'] / 100));
                        } else {
                            $totalIncentives += abs($item['incentive']);
                        }
                    }
                    array_push($summaries[$key], [
                        'type' => $k,
                        'items' => $totalItems,
                        'total' => $totalAmount,
                        'incentives' => $totalIncentives
                    ]);
                }
            }
        }

        //calculate payments
        if ($payments) {
            foreach ($payments as $pk => $payment) {
                $summaries['payments'][$pk] = 0;
                foreach ($payment as $p) {
                    $summaries['payments'][$pk] += $p->amount;
                }
            }
        }

        return response()->json(['success' => true, 'message' => '', 'data' => $summaries], $this->success);
    }

    public function summaryReport(Request $request)
    {
        $supervisor = Auth::user();
        $tripDate = ($request->trip_date) ?
            date('Y-m-d', strtotime($request->trip_date))
            : date('Y-m-d');
        $launchIds = $supervisor->vehicles->map(function ($item, $key) {
            return $item->vehicle_id;
        });
        $trip = VehicleSchedule::with('launch')
            ->whereIn('vehicle_id', $launchIds)
            ->where('schedule_date', $tripDate)->first();
        if($trip) {
            $bookings = Booking::with(
                [
                    'bookingItems' => function ($query) {
                        $query->with(['item' => function ($q) {
                            $q->with(['cabinType' => function ($q) {
                                $q->select('id', 'name', 'is_ac', 'letter');
                            }]);
                            $q->select('id', 'type_id', 'cabin_no');
                        }, 'deck.departureFrom', 'deck.departureTo']);
                        $query->select('booking_id', 'cabin_id', 'booking_type', 'status', 'price', 'discount', 'discount_type', 'vat_amount', 'charge_amount', 'incentive', 'incentive_type', 'route_name', 'charge_type', 'booking_party');
                        $query->where('status', 1);
                    },
                    'payment' => function ($query) {
                        $query->select('booking_id', 'paid_amount', 'dues');
                    },
                    'collections' => function ($query) use ($supervisor) {
                        $query->select('booking_id', 'supervisor_id', 'amount', 'payment_type');
                        $query->where('supervisor_id', $supervisor->id);
                    }
                ])
                ->select('id', 'total_payable')
                ->whereHas('bookingItems', function ($q) use ($trip) {
                    $q->where(['trip_id' => $trip->id, 'status' => 1]);
                })
                ->where('user_id', $supervisor->id)
                ->get();
            $items = [
                'payments'  => [],
                'cabins'    => [],
                'seats'     => [],
                'decks'     => [],
                'dues'      => []
            ];
            if ($bookings) {
                foreach ($bookings as $booking) {
                    foreach ($booking->bookingItems as $bookingItem) {
                        if ($bookingItem->booking_type == 'cabin') {
                            $cabinType = $bookingItem['item']['cabinType']['name'];
                            $cabinType .= ($bookingItem['item']['cabinType']['is_ac']) ? ' AC' : ' Non AC';
                            $items['cabins'][$cabinType][] = $bookingItem;
                        } elseif ($bookingItem->booking_type == 'seat') {
                            $seatType = $bookingItem['item']['cabinType']['name'];
                            $seatType .= ($bookingItem['item']['cabinType']['is_ac']) ? ' AC' : ' Non AC';
                            $items['seats'][$seatType][] = $bookingItem;
                        } else {
                            $deckType = ($bookingItem['route_name'] == null) ? $bookingItem['price'] : $bookingItem['route_name'] . ' (' . $bookingItem['price'] . ')';
                            $items['decks'][$deckType][] = $bookingItem;
                        }
                    }
                    if($booking->payment['dues'] > 0) {
                        array_push($items['dues'], ['pnr' => $booking->id, 'amount' => $booking->payment['dues']]);
                    }
                    $items['payments'][$booking->id] = $booking->collections;
                }
            }
            $payments = [];
            $summaries = [
                'info' => [
                    'name' => $supervisor->name,
                    'mobile' => $supervisor->mobile,
                    'launch_name' => $trip->launch->name,
                    'route_name' => ($trip->schedule_type == 'reverse') ? $trip->endingPoint['ghat']['name'] . ' - ' . $trip->startingPoint['ghat']['name'] : $trip->startingPoint['ghat']['name'] . ' - ' . $trip->endingPoint['ghat']['name'],
                    'leaving_at' => date('Y-m-d H:i:s', strtotime($trip->leaving_at))
                ],
                'cabins' => [],
                'seats' => [],
                'decks' => [],
                'payments' => null,
                'dues' => [],
                'refunds' => $this->supervisor->refundSummary($supervisor->id, $trip)
            ];
            foreach ($items as $key => $types) {
                if ($key == 'payments' && !empty($types)) {
                    foreach ($types as $k => $type) {
                        foreach ($type as $t) {
                            $payments[$t['payment_type']][] = $t;
                        }
                    }
                } elseif($key == 'dues') {
                    $summaries['dues'] = (array) $types;
                } else {
                    foreach ($types as $k => $bookingItems) {
                        $bookingItems = collect($bookingItems);
                        $totalItems = $bookingItems->count();
                        $totalAmount = 0;
                        $totalDues = 0;
                        $totalPaid = 0;
                        $totalIncentives = 0;
                        foreach ($bookingItems as $item) {
                            $discount = ($item->discount_type == 'percent') ? ($item['price']) * ($item['discount'] / 100) : $item->discount;
                            $totalAmount += $this->calculation->calculateItemTotal($item->toArray());
                            $totalIncentives = 0;
                            if ($item['incentive_type'] == 'percent') {
                                $totalIncentives += abs(($item['price'] - $discount) * ($item['incentive'] / 100));
                            } else {
                                $totalIncentives += abs($item['incentive']);
                            }
                        }
                        array_push($summaries[$key], [
                            'type' => $k,
                            'items' => $totalItems,
                            'total' => $totalAmount,
                            'incentives' => $totalIncentives
                        ]);
                    }
                }
            }

            //calculate payments
            if ($payments) {
                foreach ($payments as $pk => $payment) {
                    $summaries['payments'][$pk] = 0;
                    foreach ($payment as $p) {
                        $summaries['payments'][$pk] += $p->amount;
                    }
                }
            }

            return response()->json(['success' => true, 'message' => '', 'data' => $summaries], $this->success);
        }
        return response()->json(['success' => false, 'message' => 'No trips found'], 404);
    }

    public function scanHistory(Request $request)
    {
        $query = ScanLog::with(['booking.payment', 'booking.customer'])->where(['user_id' => Auth::user()->id]);
        if ($request->from) {
            $query->where('created_at', '>=', date('Y-m-d H:i:s', strtotime($request->from)));
        }
        if ($request->to) {
            $query->where('created_at', '<=', date('Y-m-d 23:59:59', strtotime($request->to)));
        }
        $logs = $query->orderByDesc('created_at')->paginate(15);

        $responseArr = [];
        if ($logs) {
            foreach ($logs as $log) {
                if($log->booking) {
                    array_push($responseArr, [
                        'id' => $log->id,
                        'booking_id' => $log->booking_id,
                        'booking_date' => date('Y-m-d H:i:s', strtotime($log->booking['created_at'])),
                        'customer_id' => $log->booking['customer_id'],
                        'customer_name' => $log->booking['customer']['name'],
                        'created_at' => date('Y-m-d H:i:s', strtotime($log->created_at))
                    ]);
                }
            }
        }
        $data = [
            'total' => $logs->total(),
            'per_page' => $logs->perPage(),
            'last_page' => $logs->lastPage(),
            'current_page' => $logs->currentPage(),
            'data' => $responseArr
        ];
        return response()->json(['success' => true, 'bookings' => $data], $this->success);
    }

    public function vehicles()
    {
        $vehicles = Vehicle::whereHas('merchant', function ($q) {
            $q->where('status', '1');
        })->pluck('name', 'id');

        $responseArr = [];
        if ($vehicles) {
            foreach ($vehicles as $k => $v) {
                array_push($responseArr, [
                    'id' => $k,
                    'name' => $v
                ]);
            }
        }

        return response()->json(['success' => true, 'data' => $responseArr], $this->success);
    }

    public function schedules(Request $request)
    {
        $query = VehicleSchedule::with('launch')->where('status', 'ACTIVE');
        if ($request->vehicle_id) {
            $query->where('vehicle_id', $request->vehicle_id);
        }
        if ($request->date_from) {
            $query->where('schedule_date', '>=', date('Y-m-d', strtotime($request->date_from)));
        }
        if ($request->date_to) {
            $query->where('schedule_date', '<=', date('Y-m-d', strtotime($request->date_to)));
        }
        $schedules = $query->paginate(15);

        $responseArr = [];
        if ($schedules) {
            foreach ($schedules as $schedule) {
                $route = $schedule->startingPoint['ghat']['name'] . ' - ' . $schedule->endingPoint['ghat']['name'];
                if ($schedule->schedule_type == 'reverse') {
                    $route = $schedule->endingPoint['ghat']['name'] . ' - ' . $schedule->startingPoint['ghat']['name'];
                }
                array_push($responseArr, [
                    'trip_id' => $schedule->id,
                    'trip_date' => $schedule->leaving_at,
                    'launch_id' => $schedule->vehicle_id,
                    'launch_name' => $schedule->launch['name'],
                    'route_name' => $route
                ]);
            }
        }

        return response()->json(['success' => true, 'schedules' => $responseArr], $this->success);
    }

    public function destinationvehicles(Request $request)
    {
        $query = VehicleSchedule::with(['launch', 'startFrom', 'stopTo',])->where('status', 'ACTIVE');

        if ($request->date_from) {
            $query->where('schedule_date', '>=', date('Y-m-d', strtotime($request->date_from)));
        }
        if ($request->date_to) {
            $query->where('schedule_date', '<=', date('Y-m-d', strtotime($request->date_to)));
        }

        if (!empty($request->where_from)) {
            $query->whereHas('startFrom', function ($q) use ($request) {
                $q->where('name', $request->where_from);
            });
        }

        if (!empty($request->where_to)) {
            $query->whereHas('stopTo', function ($q) use ($request) {
                $q->where('name', $request->where_to);
            });
        }

        $schedules = $query->groupBy('vehicle_id')->paginate(15);

        $responseArr = [];
        if ($schedules) {
            foreach ($schedules as $schedule) {
                $route = $schedule->startingPoint['ghat']['name'] . ' - ' . $schedule->endingPoint['ghat']['name'];
                array_push($responseArr, [
                    'launch_id' => $schedule->vehicle_id,
                    'launch_name' => $schedule->launch['name'],
                    'route_name' => $route
                ]);
            }
        }

        return response()->json(['success' => true, 'schedules' => $responseArr], $this->success);
    }
}
