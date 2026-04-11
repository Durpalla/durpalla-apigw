<?php

namespace App\Http\Controllers\Api\v1;

use App\Constants\AppConst;
use Illuminate\Http\JsonResponse;
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

    /**
     * Supervisor profile with token (display id SUP-xxx, not raw user id).
     * GET /api/v1/supervisor
     */
    public function profile(Request $request): JsonResponse
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated'], 401);
        }
        $type = $user->type ?? '';
        if ($type !== '' && $type !== 'supervisor') {
            return response()->json(['success' => false, 'message' => 'Supervisor access required'], 403);
        }
        $displayId = 'SUP-' . str_pad((string) $user->id, 3, '0', STR_PAD_LEFT);
        return response()->json([
            'success' => true,
            'data' => [
                'user' => [
                    'id' => $displayId,
                    'name' => $user->name,
                    'phone' => $user->mobile,
                    'role' => 'supervisor',
                ],
                'token' => $request->bearerToken(),
            ],
        ], $this->success);
    }

    public function wallet(Request $request)
    {
        $wallet = $this->supervisor->getWallet($request->all());

        return response()->json(['success' => true, 'data' => $wallet], $this->success);
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

    public function jobs(): JsonResponse
    {
        $jobs = $this->supervisor->getJobs();

        return response()->json(['success' => true, 'message' => '', 'data' => $jobs]);
    }

    public function myCart(Request $request): JsonResponse
    {
        $data = ['success' => false, 'message' => 'No booking found', 'data' => $this->supervisor->myCart($request->all())];

        return response()->json($data, $this->success);
    }

    public function sendMyCart(Request $request): JsonResponse
    {
        $data = ['success' => true, 'message' => __('Email sent successfully')];
        return response()->json($data);
    }

    public function bookingGroupWizePrint(Request $request): JsonResponse
    {
        $data = ['success' => false, 'message' => __('No trip found'), 'data' => []];
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
                    'vehicle_name' => $trip->launch->name,
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

    public function summaryReport2(Request $request): JsonResponse
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
                'vehicle_name' => $trip->launch->name,
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

    public function summaryReport(Request $request): JsonResponse
    {
        if(in_array(auth()->user()->type, ['supervisor', AppConst::AGENT_ROLE])) {
            $summaries = $this->supervisor->getSummary($request->all());
            if ($summaries !== null) {
                return response()->json(['success' => true, 'message' => '', 'data' => $summaries], $this->success);
            }
        }
        return response()->json(['success' => false, 'message' => __('No trip found')], 404);
    }

    public function sendSummary(Request $request): JsonResponse
    {
        $data = ['success' => true, 'message' => __('Email sent successfully')];
        return response()->json($data);
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
                    'vehicle_id' => $schedule->vehicle_id,
                    'vehicle_name' => $schedule->launch['name'],
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
                    'vehicle_id' => $schedule->vehicle_id,
                    'vehicle_name' => $schedule->launch['name'],
                    'route_name' => $route
                ]);
            }
        }

        return response()->json(['success' => true, 'schedules' => $responseArr], $this->success);
    }
}
