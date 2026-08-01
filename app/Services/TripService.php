<?php

namespace App\Services;

use App\Helpers\LogHelper;
use App\Models\Vehicle;
use App\Repository\Interfaces\ScheduleRepositoryInterface;
use App\Models\VehicleRoute;
use App\Models\VehicleSchedule;
use App\Services\Search\TripFederatedSearchService;
use App\Support\AuthActor;
use Illuminate\Support\Facades\DB;
use stdClass;

class TripService
{
    protected ScheduleRepositoryInterface $repository;
    private CalculationService $calculation;

    public function __construct(
        ScheduleRepositoryInterface $scheduleRepository,
        CalculationService $calculationService,
        private readonly TripFederatedSearchService $tripFederatedSearch,
    ) {
        $this->repository = $scheduleRepository;
        $this->calculation = $calculationService;
    }

    public function getSearchTrip($request): array
    {
        return $this->tripFederatedSearch->search(
            $request,
            fn ($trip) => $this->formatTripList($trip)
        );
    }

    public function getSearchTrip2($request): array
    {
        $trip_date = (!empty($request->trip_date)) ? date('Y-m-d', strtotime($request->trip_date)) : date('Y-m-d');
        $return_date = ($request->trip_return_date) ? date('Y-m-d', strtotime($request->trip_return_date)) : '';
        $results = $this->repository->searchTrip($trip_date, $return_date, $request);
        $schedules = [];

        if ($results) {
            foreach ($results as $result) {
                $startPosition = '';
                $endPosition = '';
                foreach ($result->routeProperties as $property) {
                    if (strtolower($property['ghat']['name']) == strtolower($request->trip_from)) {
                        $startPosition = $property['serial_num'];
                    }
                    if (strtolower($property['ghat']['name']) == strtolower($request->trip_to)) {
                        $endPosition = $property['serial_num'];
                    }
                }

                $onWayTripType = ($startPosition < $endPosition) ? 'straight' : 'reverse';

                $row['trip_id'] = $result->id;
                $row['route_id'] = $result->route_id;
                $row['route_name'] = $result->route['route_name'];
                $routeArr = explode('-', $result->route['route_name']);
                if ($result->schedule_type == 'reverse' && (count($routeArr) > 1)) {
                    $row['route_name'] = $routeArr[1] . '-' . $routeArr[0];
                }
                $row['vehicle_id'] = $result->vehicle_id;
                $row['launch_name'] = $result->launch['name'];
                $row['launch_photo'] = ($result->launch['photo'] != null) ? upload_asset('vehicles/' . $result->launch['photo']) : asset('default/launch.png');
                $row['schedule_date'] = $result->schedule_date;
                $row['schedule_type'] = $result->schedule_type;
                $row['leaving_at'] = date('Y-m-d H:i:s', strtotime($result->leaving_at));
                $row['leaving_time'] = date('h:i A', strtotime($result->leaving_at));
                $row['total_cabins'] = (int)$result->cabinMappings->count();
                $row['cabin_available'] = $row['total_cabins'];
                $row['total_seats'] = (int)$result->seatMappings->count();
                $row['seat_available'] = (int)$result->seatMappings->count();
                $row['total_tickets'] = $result->launch['passengers_capacity'];
                $row['ticket_available'] = $row['total_tickets'];
                $row['starting_point'] = $result->startingPoint['ghat']['name'];
                $row['ending_point'] = $result->endingPoint['ghat']['name'];
                $row['stoppages'] = [];

                $row['stoppages'][] = [
                    'id' => $result->startingPoint['id'],
                    'name' => $result->startingPoint['ghat']['name'],
                    'type' => $result->startingPoint['type']
                ];

                foreach ($result->boardingVias as $stoppage) {
                    $prop['id'] = $stoppage['id'];
                    $prop['name'] = $stoppage['ghat']['name'];
                    $prop['type'] = $stoppage['type'];
                    array_push($row['stoppages'], $prop);
                }

                $row['stoppages'][] = [
                    'id' => $result->endingPoint['id'],
                    'name' => $result->endingPoint['ghat']['name'],
                    'type' => $result->endingPoint['type']
                ];

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
                        if (in_array($cabin['cabin_id'], $books) || in_array($cabin['cabin_id'], $locks) || ($cabin['ownership'] != 'durpalla') || ($cabin['is_reserved'])) {
                            $row['cabin_available'] -= 1;
                        }
                    }
                }

                if ($result->seatMappings) {
//                     dd( $result->seatMappings );
                    foreach ($result->seatMappings as $seat) {
                        if (in_array($seat['cabin_id'], $books) || in_array($seat['cabin_id'], $locks) || ($seat['ownership'] != 'durpalla') || ($seat['is_reserved'])) {
                            $row['seat_available'] -= 1;
                        }
                    }
                }

                if ($request->vehicle_id && $result->vehicle_id == $request->vehicle_id) {
                    $schedules[] = $row;
                } else {
                    if ($result->schedule_date == $trip_date && $result->schedule_type == $onWayTripType) {
                        $schedules[] = $row;
                    } elseif ($result->schedule_date == $return_date && $result->schedule_type != $onWayTripType) {
                        $schedules[] = $row;
                    }
                }
            }
        }

        return $schedules;
    }

    public function getSuggestions($params)
    {
        return $this->repository->getListForDropdown($params)
            ->map(function ($item, $key) {
                $route = explode('-', $item->route['route_name']);
                $route_name = ($item->schedule_type == 'reverse') ? $route[1] . ' to ' . $route[0] : $route[0] . ' to ' . $route[1];
                return [
                    'id' => $item->id,
                    'name' => $item->launch->name . ' - ' . date('d-m-Y', strtotime($item->schedule_date)) . ' (' . trim($route_name) . ')'
                ];
            });
    }

    public function create(array $data)
    {
        try {
            DB::transaction(function () use ($data) {
                $vehicle = Vehicle::find($data['vehicle_id']);
                $schedule_type = (array_key_exists('schedule_type', $data)) ? 'reverse' : 'straight';
                $schedule_date = $this->calculation->createDate($data['schedule_date']);
                $schedule_time = $schedule_date . ' ' . date('H:i:s', strtotime($data['schedule_time']));
                $schedule = VehicleSchedule::where(['schedule_date' => $schedule_date, 'vehicle_id' => $data['vehicle_id'], 'status' => 'ACTIVE', 'schedule_type' => $schedule_type])->first();
                $route = VehicleRoute::find($data['route_id']);
                $operation_time = strtotime($schedule_time) + (60 * 60 * $data['operation_hour']);
                if (!$schedule) {
                    $this->repository->create(array_merge($data, [
                        'user_id' => auth()->user()->id,
                        'merchant_id' => $vehicle->merchant_id,
                        'schedule_date' => $schedule_date,
                        'leaving_at' => $schedule_time,
                        'starting_point' => ($schedule_type == 'reverse') ? $route->endingPoint['ghat_id'] : $route->startingPoint['ghat_id'],
                        'ending_point' => ($schedule_type == 'reverse') ? $route->startingPoint['ghat_id'] : $route->endingPoint['ghat_id'],
                        'operation_timeline' => date('Y-m-d H:i:s', $operation_time),
                        'schedule_type' => $schedule_type
                    ]));
                } else {
                    throw new \Exception('Vehicle schedule already exists');
                }
            });
        } catch (\Exception $e) {
            dd($e->getMessage());
        }
    }

    public function formatTripList($trip)
    {
        $user = auth()->user();
        return [
            'trip_id' => $trip->id,
            'route_id' => $trip->route_id,
            'route_name' => $trip->startFrom['name'] . ' - ' . $trip->stopTo['name'],
            'vehicle_id' => $trip->vehicle_id,
            'vehicle_name' => $trip->vehicle['name'],
            'nid_check' => $trip->vehicle['nid_verification_check'],
            'is_ac' => $trip->vehicle['ac_available'],
            'vehicle_photo' => ($trip->vehicle['photo']) ? upload_asset('vehicles/' . $trip->vehicle['photo']) : asset('default/launch.png'),
            'schedule_date' => $trip->schedule_date,
            'schedule_type' => $trip->schedule_type,
            'leaving_at' => date('Y-m-d H:i:s', strtotime($trip->leaving_at)),
            'leaving_time' => date('h:i A', strtotime($trip->leaving_at)),
            'operation_end' => $trip->operation_timeline,
            'total_cabins' => $trip->mappings->where('type', 'cabin')->count(),
            'total_seats' => $trip->mappings->where('type', 'seat')->count(),
            'default_tab' => $trip->vehicle['default_tab'],
            'default_floor' => $trip->vehicle['default_floor'],
            'cabin_available' => $trip->mappings->where('type', 'cabin')->filter(function ($item, $key) use ($user) {
                $status = true;
                if ($item->is_reserved || $item->is_locked || $item->booked) {
                    $status = false;
                }
                $type = AuthActor::ownershipType($user);
                if ($type !== $item->ownership) {
                    $status = false;
                }
                return $status;
            })->count(),
            'seat_available' => $trip->mappings->where('type', 'seat')->filter(function ($item, $key) use ($user) {
                $status = true;
                if ($item->is_reserved || $item->is_locked || $item->booked) {
                    $status = false;
                }
                $type = AuthActor::ownershipType($user);
                if ($type !== $item->ownership) {
                    $status = false;
                }
                return $status;
            })->count(),
            'total_tickets' => $trip->vehicle['passengers_capacity'],
            'ticket_available' => $trip->vehicle['passengers_capacity'],
            'starting_point' => $trip->startFrom['name'],
            'ending_point' => $trip->stopTo['name'],
            'stoppages' => $this->formatStoppages($trip),
            'service_type' => $trip->vehicle->vehicle_type
        ];
    }

    public function formatTriplayout($trip, $floor = null): array
    {
        $user = auth()->user();
        $cabins = [];
        $seats = [];
        $cabin_types = [];
        $seat_types = [];
        $layouts[$trip->id] = [];

        // Build rows
        $trip->mappings->each(function ($cabin) use ($trip, &$cabins, &$seats, &$cabin_types, &$seat_types, $user) {
            $row = []; // IMPORTANT: reset per-iteration

            $row['item_id'] = $cabin->id;
            $row['trip_id'] = $trip->id;
            $row['trip_date'] = date('Y-m-d H:i:s', strtotime($trip->leaving_at));
            $row['route_id'] = $trip->route_id;
            $row['vehicle_id'] = $cabin['vehicle_id'];
            $row['vehicle_name'] = $trip->vehicle['name'];
            $row['nid_check'] = $trip->vehicle['nid_verification_check'];
            $row['booking_id'] = $cabin['booking_id'];
            $row['merchant_id'] = $trip->merchant_id;
            $row['cabin_id'] = $cabin->id;
            $row['cabin_type_id'] = $cabin['type_id'];
            $row['cabin_type'] = $cabin['type'];
            $row['cabin_floor'] = $cabin['floor'];
            $row['cabin_no'] = ($cabin->cabinType)
                ? $cabin['cabinType']['letter'] . '-' . $cabin['cabin_no']
                : $cabin['cabin_no'];
            $row['fare'] = $cabin['fare'];
            $row['cabin_is_ac'] = ($cabin->cabinType && $cabin['cabinType']['is_ac']) ? 1 : 0;
            $row['capacity'] = $cabin['passenger_capacity'];
            $row['cabin_row'] = $cabin['cabin_row'];
            $row['cabin_position'] = $cabin['cabin_position'];
            $row['description'] = ($cabin->cabinType)
                ? $cabin['cabinType']['name'] . ' - ' . $cabin['cabinType']['letter'] . '-' . $cabin['cabin_no']
                : 'Cabin - ' . $cabin->cabin_no;
            $row['ownership'] = $cabin->ownership;
            $row['service_charge'] = $cabin->service_charge;

            // status gating
            $type = AuthActor::ownershipType($user);
            $row['status'] = (($cabin->is_reserved == 1) || $cabin->booked == 1 || $cabin->is_locked == 1) ? 0 : 1;
            if ($type !== $cabin->ownership) $row['status'] = 0;
            if ($cabin->is_advance) $row['status'] = 9;

            LogHelper::debug('CABIN_STATUS ' . $row['cabin_no'], [
                'cabin_id' => $cabin->id,
                'cabin_type' => $cabin->type,
                'user_type' => $type,
                'ownership' => $cabin->ownership,
            ]);

            $row['cabin_class'] = $row['status'] ? 'cabin-active' : 'cabin-disable';

            if ($cabin['type'] === 'cabin') {
                $cabins[] = $row;
                if ($cabin->type_id > 0) {
                    $isAc = ($cabin->cabinType->is_ac) ? 'AC' : 'Non-AC';
                    $cabin_types[$cabin['type_id']] = $cabin->cabinType
                        ? $cabin['cabinType']['name'] . ' (' . $isAc . ')'
                        : 'Unknown';
                }
            } elseif ($cabin['type'] === 'seat') {
                $seats[] = $row;
                if ($cabin->type_id > 0) {
                    $isAc = ($cabin->cabinType->is_ac) ? 'AC' : 'Non-AC';
                    $seat_types[$cabin['type_id']] = $cabin->cabinType
                        ? $cabin['cabinType']['name'] . ' (' . $isAc . ')'
                        : 'Unknown';
                }
            }
        });

        // Helpers to keep "map of rows" shape consistent
        $emptyRowMap = fn() => new stdClass();  // -> {}
        $ensureRowMap = function ($grouped) {
            // $grouped is expected to be an associative array like ['1' => [ ... ], '2' => [ ... ]]
            return !empty($grouped) ? $grouped : new stdClass();
        };

        $cabinsLayout = [];
        $seatsLayout = [];

        if ($floor === null) {
            // All floors: always return 4 keys with {} when empty
            if ($cabins) {
                $first = _my_layout(_my_group_by(collect($cabins)->filter(fn($i) => $i['cabin_floor'] === 1), 'cabin_row'));
                $second = _my_layout(_my_group_by(collect($cabins)->filter(fn($i) => $i['cabin_floor'] === 2), 'cabin_row'));
                $third = _my_layout(_my_group_by(collect($cabins)->filter(fn($i) => $i['cabin_floor'] === 3), 'cabin_row'));
                $fourth = _my_layout(_my_group_by(collect($cabins)->filter(fn($i) => $i['cabin_floor'] === 4), 'cabin_row'));
                ksort($first);
                ksort($second);
                ksort($third);
                ksort($fourth);

                $cabinsLayout = [
                    'first_floor' => $ensureRowMap($first),
                    'second_floor' => $ensureRowMap($second),
                    'third_floor' => $ensureRowMap($third),
                    'fourth_floor' => $ensureRowMap($fourth),
                ];
            } else {
                $cabinsLayout = [
                    'first_floor' => $emptyRowMap(),
                    'second_floor' => $emptyRowMap(),
                    'third_floor' => $emptyRowMap(),
                    'fourth_floor' => $emptyRowMap(),
                ];
            }

            if ($seats) {
                $first = _my_layout(_my_group_by(collect($seats)->filter(fn($i) => $i['cabin_floor'] === 1), 'cabin_row'));
                $second = _my_layout(_my_group_by(collect($seats)->filter(fn($i) => $i['cabin_floor'] === 2), 'cabin_row'));
                $third = _my_layout(_my_group_by(collect($seats)->filter(fn($i) => $i['cabin_floor'] === 3), 'cabin_row')); // fixed
                $fourth = _my_layout(_my_group_by(collect($seats)->filter(fn($i) => $i['cabin_floor'] === 4), 'cabin_row')); // fixed
                ksort($first);
                ksort($second);
                ksort($third);
                ksort($fourth);

                $seatsLayout = [
                    'first_floor' => $ensureRowMap($first),
                    'second_floor' => $ensureRowMap($second),
                    'third_floor' => $ensureRowMap($third),
                    'fourth_floor' => $ensureRowMap($fourth),
                ];
            } else {
                $seatsLayout = [
                    'first_floor' => $emptyRowMap(),
                    'second_floor' => $emptyRowMap(),
                    'third_floor' => $emptyRowMap(),
                    'fourth_floor' => $emptyRowMap(),
                ];
            }
        } else {
            // Single floor: return just the row map for that floor ({} when empty)
            if ($cabins) {
                $cabinsLayout = _my_layout(_my_group_by(collect($cabins)->filter(fn($i) => $i['cabin_floor'] == $floor), 'cabin_row'));
                ksort($cabinsLayout);
                $cabinsLayout = $ensureRowMap($cabinsLayout);
            } else {
                $cabinsLayout = $emptyRowMap();
            }

            if ($seats) {
                $seatsLayout = _my_layout(_my_group_by(collect($seats)->filter(fn($i) => $i['cabin_floor'] == $floor), 'cabin_row'));
                ksort($seatsLayout);
                $seatsLayout = $ensureRowMap($seatsLayout);
            } else {
                $seatsLayout = $emptyRowMap();
            }
        }

        $cabinTypes = [['value' => 0, 'label' => 'All']];
        $seatTypes = [['value' => 0, 'label' => 'All']];
        collect($cabin_types)->each(fn($item, $key) => $cabinTypes[] = ['value' => $key, 'label' => $item]);
        collect($seat_types)->each(fn($item, $key) => $seatTypes[] = ['value' => $key, 'label' => $item]);

        $allItems = array_merge($cabins, $seats);
        $cabinRows = 1;
        $maxPosition = 1;
        foreach ($allItems as $item) {
            $rowNum = (int) ($item['cabin_row'] ?? 0);
            $posNum = (int) ($item['cabin_position'] ?? 0);
            if ($rowNum > $cabinRows) {
                $cabinRows = $rowNum;
            }
            if ($posNum > $maxPosition) {
                $maxPosition = $posNum;
            }
        }

        return [
            'id' => $trip->id,
            'trip_id' => $trip->id,
            'cabin_rows' => $cabinRows,
            'max_position' => $maxPosition,
            'rowClass' => '',
            'vehicle_id' => $trip->vehicle_id,
            'number_of_floor' => $trip->vehicle['number_of_floor'],
            'floors' => $this->formatFloors($trip->vehicle['number_of_floor']),
            'merchant_id' => $trip->vehicle['merchant_id'],
            'route_id' => $trip->route_id,
            'vehicle_name' => $trip->vehicle['name'],
            'vehicle_photo' => ($trip->vehicle['photo']) ? upload_asset('vehicles/' . $trip->vehicle['photo']) : asset('default/launch.png'),
            'is_ac' => $trip->vehicle['ac_available'],
            'leaving_time' => date('h:i A', strtotime($trip->leaving_at)),
            'starting_point' => $trip->startFrom['name'],
            'ending_point' => $trip->stopTo['name'],
            'route_name' => $trip->startFrom['name'] . ' - ' . $trip->stopTo['name'],
            'vehicle_route' => $trip->startFrom['name'] . ' - ' . $trip->stopTo['name'],
            'schedule_date' => date('Y-m-d H:i:s', strtotime($trip->leaving_at)),
            // ALWAYS objects (maps) per your chosen shape:
            'cabins' => $cabinsLayout,  // maps of rows; {} when empty
            'seats' => $seatsLayout,   // maps of rows; {} when empty
            'decks' => $this->formatDecks($trip),
            'cabin_types' => $cabinTypes,
            'seat_types' => $seatTypes,
            'stoppages' => $this->formatStoppages($trip, true),
            'vat_amount' => getOption('vat_amount', 0),
            'vat_applicable_to' => $trip->vehicle['merchant']['vat_applicable_to'],
            'vat_visibility' => $trip->vehicle['merchant']['vat_visibility'],
        ];
    }

    public function formatStoppages($trip, $last = true)
    {
        $stoppages = [
            [
                'id' => $trip->startFrom['id'],
                'name' => $trip->startFrom['name'],
                'type' => 'start'
            ]
        ];

        if ($trip->boardingVias) {
            $vias = $trip->boardingVias->toArray();
            if ($trip->schedule_type == 'reverse')
                krsort($vias);

            collect($vias)->each(function ($item, $key) use (&$stoppages) {
                array_push($stoppages, [
                    'id' => $item['ghat']['id'],
                    'name' => $item['ghat']['name'],
                    'type' => 'via'
                ]);
            });
        }
        if ($last) {
            array_push($stoppages, [
                'id' => $trip->stopTo['id'],
                'name' => $trip->stopTo['name'],
                'type' => 'end'
            ]);
        }

        return $stoppages;
    }

    private function formatDecks($trip)
    {
        return $trip->decks->map(function ($item, $key) use ($trip) {
            return [
                'id' => $item->id,
                'from' => ($item->departureFrom) ? $item->departureFrom['ghat']['name'] : null,
                'to' => ($item->departureTo) ? $item->departureTo['ghat']['name'] : null,
                'fare' => ($trip->schedule_type == 'reverse') ? $item->reverse_fare : $item->fare
            ];
        });
    }

    private function formatFloors($number_of_floor): array
    {
        $floors = [];
        $floor_levels = config('constants.floors');
        for ($i = 1; $i <= $number_of_floor; $i++) {
            $floors[] = ['label' => $floor_levels[$i], 'value' => $i];
        }

        return $floors;
    }
}
