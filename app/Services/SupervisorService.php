<?php


namespace App\Services;


use App\Constants\AppConst;
use Illuminate\Support\Facades\Auth;
use App\Models\Booking;
use App\Repository\Interfaces\BookingRepositoryInterface;
use App\Repository\Interfaces\CancellationRepositoryInterface;
use App\Repository\Interfaces\ScheduleRepositoryInterface;
use App\Models\VehicleSchedule;

class SupervisorService
{
    private $schedule;
    private $cancellation;
    private $supervisor;
    private $booking;
    private $calculation;
    private $balance;

    public function __construct(
        ScheduleRepositoryInterface $scheduleRepository,
        CancellationRepositoryInterface $cancellationRepository,
        BookingRepositoryInterface $bookingRepository,
        CalculationService $calculationService,
        BalanceService $balanceService
    )
    {
        $this->schedule = $scheduleRepository;
        $this->cancellation = $cancellationRepository;
        $this->booking = $bookingRepository;
        $this->calculation = $calculationService;
        $this->balance = $balanceService;
    }

    public function getJobs()
    {
        $supervisor = Auth::user();
        $launchIds = $supervisor->vehicles->map(function ($item, $key) {
            return $item->id;
        });

        $schedules = $this->schedule->getSupervisorJobs($supervisor);
        $currentTime = time();
        return $schedules->map(function ($item, $k) use ($currentTime) {
            $schedule = $item;
            $leavingTime = strtotime($item->leaving_at);
            $operationEnd = $leavingTime + ($item->operation_hour * 60 * 60);
            $data = [
                'id' => $item->id,
                'schedule_date' => $item->leaving_at,
                'operation_end' => date('Y-m-d H:i:s', $operationEnd),
                'vehicle_id' => $item->vehicle_id,
                'vehicle_name' => $item->launch->name,
                'status' => 'complete',
                'assigned_persons' => []
            ];
            if (($currentTime >= $leavingTime && $currentTime <= $operationEnd) || $item->schedule_date == date('Y-m-d')) {
                $data['status'] = 'current';
            } elseif ($currentTime < $leavingTime) {
                $data['status'] = 'upcoming';
            }
            $item->launch->supervisors->each(function ($item, $key) use ($schedule, &$data) {
                if ($schedule->leaving_at >= $item->created_at) {
                    array_push($data['assigned_persons'], [
                        'name' => $item->user->name,
                        'mobile' => $item->user->mobile,
                        'designation' => $item->user->designation['name']
                    ]);
                }
            });
            return $data;
        });
    }

    public function refundSummary($supervisorId, $trip)
    {
        $cancellations = $this->cancellation->getSupervisorCancellations($supervisorId, $trip->id);
        $responseArr = ['info' => [], 'payments' => null];
        if ($cancellations->count()) {
            $lists = $cancellations->map(function ($item, $key) {
                $booking = $item->booking ?? \App\Models\Booking::query()->find($item->booking_id);

                return [
                    'booking_id' => $item->booking_id,
                    'pnr' => $booking ? $booking->publicReference() : (string) $item->booking_id,
                    'type' => $item->payment_method,
                    'amount' => $item->refund_amount
                ];
            });
            $lists->groupBy('pnr')
                ->each(function ($items, $key) use (&$responseArr) {
                    array_push($responseArr['info'], [
                        'pnr' => $key,
                        'amount' => $items->sum('amount')
                    ]);
                });
            $lists->groupBy('type')->each(function ($items, $key) use (&$responseArr) {
                $responseArr['payments'][$key] = $items->sum('amount');
            });
            return $responseArr;
        }
        return null;
    }

    public function getSummary(array $data)
    {
        $supervisor = Auth::user();
        $tripDate = (array_key_exists('trip_date', $data) && $data['trip_date'] !== null) ?
            date('Y-m-d', strtotime($data['trip_date']))
            : date('Y-m-d');
        $launchIds = $supervisor->vehicles->map(function ($item, $key) {
            return $item->vehicle_id;
        });
        $trip = VehicleSchedule::with('launch')
            ->whereIn('vehicle_id', $launchIds)
            ->where('schedule_date', $tripDate)->first();
        if ($trip) {
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
                        $query->select('payments.id', 'payments.booking_id', 'payments.paid_amount', 'payments.dues');
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
                'payments' => [],
                'cabins' => [],
                'seats' => [],
                'decks' => [],
                'dues' => []
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
                    if ($booking->payment['dues'] > 0) {
                        array_push($items['dues'], ['pnr' => $booking->publicReference(), 'amount' => $booking->payment['dues']]);
                    }
                    $items['payments'][$booking->id] = $booking->collections;
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
                'payments' => null,
                'dues' => [],
                'refunds' => $this->refundSummary($supervisor->id, $trip)
            ];
            foreach ($items as $key => $types) {
                if ($key == 'payments' && !empty($types)) {
                    foreach ($types as $k => $type) {
                        foreach ($type as $t) {
                            $payments[$t['payment_type']][] = $t;
                        }
                    }
                } elseif ($key == 'dues') {
                    $summaries['dues'] = (array)$types;
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
            return $summaries;
        }
        return null;
    }

    public function myCart(array $data)
    {
        $response = [];
        $tripDate = (array_key_exists('trip_date', $data) && $data['trip_date'] !== null) ?
            date('Y-m-d', strtotime($data['trip_date'])) :
            date('Y-m-d');
        $query = Booking::with(['payment', 'bookingItems.item.cabinType', 'collections', 'customer'])
            ->withCount('collections')
            ->where(['user_id' => Auth::user()->id])
            ->where('total_payable', '>', 0);

        if (array_key_exists('pnr', $data) && $data['pnr'] !== null) {
            $raw = trim((string) $data['pnr']);
            $normalized = app(\App\Services\BookingPnrService::class)->normalize($raw);
            if ($normalized !== null) {
                $query->where('pnr', $normalized);
            } elseif (ctype_digit($raw)) {
                // Trusted supervisor channel may still resolve numeric internal ids.
                $query->where('id', (int) $raw);
            } else {
                $query->where('pnr', $raw);
            }
        } else {
            $query->whereHas('bookingItems', function ($q) use ($tripDate) {
                $q->where(['trip_date' => $tripDate, 'status' => 1]);
            });
            if (array_key_exists('type', $data) && $data['type'] === 'complete') {
                $query->whereHas('payment', function ($q) use ($data) {
                    $q->where('dues', 0);
                })->has('collections', '=', 1);
            } elseif (array_key_exists('type', $data) && $data['type'] === 'due') {
                $query->whereHas('payment', function ($q) use ($data) {
                    $q->where('dues', '>', 0);
                });
            } elseif (array_key_exists('type', $data) && $data['type'] === 'advance') {
                $query->has('collections', '>', 1);
                $query->whereHas('payment', function ($q) {
                    $q->where('dues', '=', 0);
                });
            }
        }
        $bookings = $query->orderByDesc('created_at')->paginate(15);
        if ($bookings) {
            foreach ($bookings as $booking) {
                $payments = [];
                $collections = [];
                $countCollections = $booking->collections->count();
                $bookingStatus = ($booking->payment['dues'] == 0 && $countCollections > 1) ? 'advance' : (($booking->payment['dues'] == 0 && $countCollections == 1) ? 'complete' : 'due');
                foreach ($booking->collections as $key => $collection) {
                    if ($countCollections > 1) {
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
                $booking->bookingItems->each(function ($item, $key) use (&$items) {
                    if ($item->booking_type != 'deck') {
                        $items[$item->booking_type][] = (($item->item['cabinType']) ? $item->item['cabinType']['letter'] . '-' : '') . $item->item['cabin_no'];
                    } else {
                        $passenger = json_decode($item->passenger);
                        $items['deck'] += ($passenger) ? $passenger->person : 1;
                    }
                });
                array_push($response, [
                    'booking_id' => $booking->id,
                    'order_id' => $booking->id,
                    'pnr' => $booking->publicReference(),
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

        return $response;
    }

    public function getWallet($params): array
    {
        $user = Auth::user();
        $lists = [];
        $groups = $this->booking->getOfficerBookings($user->id, $params)->flatMap(function ($item, $key) {
            return $item->bookingItems;
        })
            ->groupBy('booking_type')
            ->each(function ($item, $key) use (&$lists) {
                $data = ['type' => $key, 'total_bookings' => 0, 'total_amount' => 0, 'total_incentives' => 0];
                $item->each(function($item, $key) use(&$data, &$lists) {
                    $data['total_bookings'] += 1;
                    $data['total_amount'] += $this->calculation->calculateItemTotal($item->toArray());
                    $data['total_incentives'] += $this->calculation->calculateAgentCommission($item->toArray());
                });
                array_push($lists, $data);
            });

        return [
            'balance' => $this->balance->getMyBalance($user->id),
            'total_bookings' => $groups->sum('total_bookings'),
            'total_incentives' => $groups->sum('total_incentives'),
            'lists' => $lists
        ];
    }
}
