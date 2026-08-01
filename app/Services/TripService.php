<?php

namespace App\Services;

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
        $sofas = [];
        $cabin_types = [];
        $seat_types = [];
        $sofa_types = [];
        $vehicle = $trip->vehicle;
        $vehicleName = $vehicle?->name ?? '';
        $nidCheck = $vehicle?->nid_verification_check ?? 0;
        $ownershipType = AuthActor::ownershipType($user);
        $mappings = $trip->mappings ?? collect();

        // Build rows
        $mappings->each(function ($cabin) use (
            $trip,
            &$cabins,
            &$seats,
            &$sofas,
            &$cabin_types,
            &$seat_types,
            &$sofa_types,
            $vehicleName,
            $nidCheck,
            $ownershipType
        ) {
            $row = []; // IMPORTANT: reset per-iteration
            $cabinType = $cabin->cabinType;

            $row['item_id'] = $cabin->id;
            $row['trip_id'] = $trip->id;
            $row['trip_date'] = date('Y-m-d H:i:s', strtotime((string) $trip->leaving_at));
            $row['route_id'] = $trip->route_id;
            $row['vehicle_id'] = $cabin->vehicle_id;
            $row['vehicle_name'] = $vehicleName;
            $row['nid_check'] = $nidCheck;
            $row['booking_id'] = $cabin->booking_id;
            $row['merchant_id'] = $trip->merchant_id;
            $row['cabin_id'] = $cabin->id;
            $row['cabin_type_id'] = $cabin->type_id;
            $row['cabin_type'] = $cabin->type;
            $row['cabin_floor'] = $cabin->floor;
            $row['cabin_no'] = $cabinType
                ? (($cabinType->letter ?? '') . '-' . $cabin->cabin_no)
                : $cabin->cabin_no;
            $row['fare'] = $cabin->fare;
            $row['cabin_is_ac'] = ($cabinType && ($cabinType->is_ac ?? false)) ? 1 : 0;
            $row['capacity'] = $cabin->passenger_capacity;
            $row['cabin_row'] = $cabin->cabin_row;
            $row['cabin_position'] = $cabin->cabin_position;
            $row['description'] = $cabinType
                ? (($cabinType->name ?? 'Cabin') . ' - ' . ($cabinType->letter ?? '') . '-' . $cabin->cabin_no)
                : 'Cabin - ' . $cabin->cabin_no;
            $row['ownership'] = $cabin->ownership;
            $row['service_charge'] = $cabin->service_charge;

            // status gating
            $row['status'] = (($cabin->is_reserved == 1) || $cabin->booked == 1 || $cabin->is_locked == 1) ? 0 : 1;
            if ($ownershipType !== $cabin->ownership) {
                $row['status'] = 0;
            }
            if ($cabin->is_advance) {
                $row['status'] = 9;
            }

            $row['cabin_class'] = $row['status'] ? 'cabin-active' : 'cabin-disable';

            $typeLabel = $cabinType
                ? ($cabinType->name . ' (' . (($cabinType->is_ac ?? false) ? 'AC' : 'Non-AC') . ')')
                : 'Unknown';

            if ($cabin->type === 'cabin') {
                $cabins[] = $row;
                if ($cabin->type_id > 0) {
                    $cabin_types[$cabin->type_id] = $typeLabel;
                }
            } elseif ($cabin->type === 'seat') {
                $seats[] = $row;
                if ($cabin->type_id > 0) {
                    $seat_types[$cabin->type_id] = $typeLabel;
                }
            } elseif ($cabin->type === 'sofa') {
                $sofas[] = $row;
                if ($cabin->type_id > 0) {
                    $sofa_types[$cabin->type_id] = $typeLabel;
                }
            }
        });

        // Helpers to keep "map of rows" shape consistent
        $emptyRowMap = fn() => new stdClass();  // -> {}
        $ensureRowMap = function ($grouped) {
            // $grouped is expected to be an associative array like ['1' => [ ... ], '2' => [ ... ]]
            return !empty($grouped) ? $grouped : new stdClass();
        };

        $sortLayout = static function ($layout) {
            if (!is_array($layout)) {
                return [];
            }
            ksort($layout);
            return $layout;
        };
        $floorOf = static fn ($item, int $n) => (int) ($item['cabin_floor'] ?? 0) === $n;
        $layoutForFloor = static function (array $items, int $floorNum) use ($floorOf, $sortLayout) {
            $filtered = array_values(array_filter($items, static fn ($i) => $floorOf($i, $floorNum)));

            return $sortLayout(_my_layout(_my_group_by($filtered, 'cabin_row')));
        };
        $buildAllFloors = function (array $items) use ($emptyRowMap, $ensureRowMap, $layoutForFloor) {
            if (!$items) {
                return [
                    'first_floor' => $emptyRowMap(),
                    'second_floor' => $emptyRowMap(),
                    'third_floor' => $emptyRowMap(),
                    'fourth_floor' => $emptyRowMap(),
                ];
            }

            return [
                'first_floor' => $ensureRowMap($layoutForFloor($items, 1)),
                'second_floor' => $ensureRowMap($layoutForFloor($items, 2)),
                'third_floor' => $ensureRowMap($layoutForFloor($items, 3)),
                'fourth_floor' => $ensureRowMap($layoutForFloor($items, 4)),
            ];
        };

        if ($floor === null) {
            // All floors: always return 4 keys with {} when empty
            $cabinsLayout = $buildAllFloors($cabins);
            $seatsLayout = $buildAllFloors($seats);
            $sofasLayout = $buildAllFloors($sofas);
        } else {
            // Single floor: return just the row map for that floor ({} when empty)
            $floorNum = (int) $floor;
            $cabinsLayout = $cabins
                ? $ensureRowMap($layoutForFloor($cabins, $floorNum))
                : $emptyRowMap();
            $seatsLayout = $seats
                ? $ensureRowMap($layoutForFloor($seats, $floorNum))
                : $emptyRowMap();
            $sofasLayout = $sofas
                ? $ensureRowMap($layoutForFloor($sofas, $floorNum))
                : $emptyRowMap();
        }

        $cabinTypes = [['value' => 0, 'label' => 'All']];
        $seatTypes = [['value' => 0, 'label' => 'All']];
        $sofaTypes = [['value' => 0, 'label' => 'All']];
        collect($cabin_types)->each(fn($item, $key) => $cabinTypes[] = ['value' => $key, 'label' => $item]);
        collect($seat_types)->each(fn($item, $key) => $seatTypes[] = ['value' => $key, 'label' => $item]);
        collect($sofa_types)->each(fn($item, $key) => $sofaTypes[] = ['value' => $key, 'label' => $item]);

        $allItems = array_merge($cabins, $seats, $sofas);
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

        $merchant = $vehicle?->merchant ?? $trip->merchant;
        $startName = $trip->startFrom?->name ?? '';
        $endName = $trip->stopTo?->name ?? '';
        // Cap floors — corrupt number_of_floor values used to OOM formatFloors().
        $floorsCount = max(1, min(11, (int) ($vehicle?->number_of_floor ?? 1)));
        $photo = $vehicle?->photo;

        return [
            'id' => $trip->id,
            'trip_id' => $trip->id,
            'cabin_rows' => $cabinRows,
            'max_position' => $maxPosition,
            'rowClass' => '',
            'vehicle_id' => $trip->vehicle_id,
            'number_of_floor' => $floorsCount,
            'floors' => $this->formatFloors($floorsCount),
            'default_floor' => $vehicle?->default_floor ?? 1,
            'default_tab' => $vehicle?->default_tab ?? null,
            'merchant_id' => $vehicle?->merchant_id ?? $trip->merchant_id,
            'route_id' => $trip->route_id,
            'vehicle_name' => $vehicle?->name ?? '',
            'vehicle_photo' => $photo
                ? upload_asset('vehicles/' . $photo)
                : asset('default/launch.png'),
            'is_ac' => $vehicle?->ac_available ?? 0,
            'leaving_time' => date('h:i A', strtotime((string) $trip->leaving_at)),
            'starting_point' => $startName,
            'ending_point' => $endName,
            'route_name' => trim($startName . ' - ' . $endName, ' -'),
            'vehicle_route' => trim($startName . ' - ' . $endName, ' -'),
            'schedule_date' => date('Y-m-d H:i:s', strtotime((string) $trip->leaving_at)),
            // ALWAYS objects (maps) — Flutter/web consume these directly:
            'cabins' => $cabinsLayout,
            'seats' => $seatsLayout,
            'sofas' => $sofasLayout,
            'decks' => $this->formatDecks($trip),
            'cabin_types' => $cabinTypes,
            'seat_types' => $seatTypes,
            'sofa_types' => $sofaTypes,
            'stoppages' => $this->formatStoppages($trip, true),
            'vat_amount' => getOption('vat_amount', 0),
            'vat_applicable_to' => $merchant?->vat_applicable_to ?? null,
            'vat_visibility' => $merchant?->vat_visibility ?? null,
        ];
    }

    public function formatStoppages($trip, $last = true)
    {
        $stoppages = [];

        if ($trip->startFrom) {
            $stoppages[] = [
                'id' => $trip->startFrom->id,
                'name' => $trip->startFrom->name,
                'type' => 'start',
            ];
        }

        if ($trip->boardingVias) {
            $vias = $trip->boardingVias;
            if ($trip->schedule_type == 'reverse') {
                $vias = $vias->sortByDesc(fn ($via) => $via->id)->values();
            }

            $vias->each(function ($item) use (&$stoppages) {
                $ghat = $item->ghat;
                if (!$ghat || empty($ghat->id)) {
                    return;
                }
                $stoppages[] = [
                    'id' => $ghat->id,
                    'name' => $ghat->name ?? '',
                    'type' => 'via',
                ];
            });
        }

        if ($last && $trip->stopTo) {
            $stoppages[] = [
                'id' => $trip->stopTo->id,
                'name' => $trip->stopTo->name,
                'type' => 'end',
            ];
        }

        return $stoppages;
    }

    private function formatDecks($trip)
    {
        try {
            $decks = $trip->decks ?? collect();

            return $decks->map(function ($item) use ($trip) {
                return [
                    'id' => $item->id,
                    'from' => $item->departureFrom?->ghat?->name,
                    'to' => $item->departureTo?->ghat?->name,
                    'fare' => ($trip->schedule_type == 'reverse') ? $item->reverse_fare : $item->fare,
                ];
            })->values();
        } catch (\Throwable $e) {
            report($e);

            return collect();
        }
    }

    private function formatFloors($number_of_floor): array
    {
        $floors = [];
        $count = max(1, min(11, (int) $number_of_floor));
        $floor_levels = config('constants.floors', []);
        for ($i = 1; $i <= $count; $i++) {
            $floors[] = [
                'label' => $floor_levels[$i] ?? ($i . ' Floor'),
                'value' => $i,
            ];
        }

        return $floors;
    }
}
