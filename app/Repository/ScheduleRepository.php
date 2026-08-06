<?php

namespace App\Repository;

use App\Constants\AppConst;
use App\Models\Ghat;
use App\Models\VehicleSchedule;
use App\Repository\Interfaces\ScheduleRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use App\Models\User;

class ScheduleRepository extends BaseRepository implements ScheduleRepositoryInterface
{
    protected $model;

    public function __construct(VehicleSchedule $model)
    {
        parent::__construct($model);
    }

    public function all(): Collection
    {
        return $this->model->all();
    }

    public function create(array $data)
    {
        return parent::create($data);
    }

    public function update(array $data, $id)
    {
        return parent::update($data, $id);
    }

    public function searchTrip($request)
    {
        $this->normalizeTripSearchRequest($request);

        $data = $request->all();
        // Load only what trip list formatters need (layout loads mappings separately).
        $query = $this->model->with([
            'vehicle',
            'launch',
            'route',
            'startFrom',
            'stopTo',
            'startingPoint.ghat',
            'endingPoint.ghat',
            'mappings.cabinType',
        ])
            ->withCount('cabins')
            // status + schedule_date first for vs_status_date* indexes.
            ->where('status', AppConst::SCHEDULE_ACTIVE);

        // Customer/agent public search: only Durpalla-admin approved vehicles.
        \App\Support\PublicListingVisibility::applyApprovedVehicle($query, 'launch');

        if ($request->filled('trip_id')) {
            $query->where('id', $request->input('trip_id'));
        } elseif ($request->filled('vehicle_id')) {
            $query->where('vehicle_id', $data['vehicle_id'])
                ->where('leaving_at', '>=', date('Y-m-d H:i:s'))
                ->orderBy('leaving_at', 'ASC');
        } else {
            $tripDate = $request->input('trip_date', date('Y-m-d'));
            if ($request->filled('return_date')) {
                $returnTripDate = $request->input('return_trip_date', $tripDate);
                $query->whereIn('schedule_date', [$tripDate, $returnTripDate]);
            } else {
                $query->where('schedule_date', $tripDate);
            }
        }

        $serviceType = null;
        if (array_key_exists('type', $data) && !empty($data['type'])) {
            $serviceType = $data['type'];
        } elseif (array_key_exists('service_type', $data) && !empty($data['service_type'])) {
            $serviceType = $data['service_type'];
        }

        if ($serviceType) {
            $query->whereHas('launch', function ($q) use ($serviceType) {
                $q->where('vehicle_type', $serviceType);
            });
        }

        if ($request->filled('trip_from') && $request->filled('trip_to')) {
            $fromIds = \App\Support\GhatPlaceName::resolveGhatIds((string) $data['trip_from']);
            $toIds = \App\Support\GhatPlaceName::resolveGhatIds((string) $data['trip_to']);
            $tableName = $this->model->getTable();

            if ($fromIds === [] || $toIds === []) {
                $query->whereRaw('0 = 1');
            } else {
                $fromPlaceholders = implode(',', array_fill(0, count($fromIds), '?'));
                $toPlaceholders = implode(',', array_fill(0, count($toIds), '?'));
                // ghat_id IN (...) uses rp_route_ghat_idx — avoid functions on ghats.name.
                $query->whereRaw("
                    EXISTS (
                        SELECT 1 FROM route_properties as rp1
                        JOIN route_properties as rp2 ON rp1.route_id = rp2.route_id
                        WHERE rp1.route_id = {$tableName}.route_id
                        AND rp1.ghat_id IN ({$fromPlaceholders})
                        AND rp2.ghat_id IN ({$toPlaceholders})
                        AND rp1.ghat_id <> rp2.ghat_id
                        AND (
                            (
                                {$tableName}.schedule_type = 'straight'
                                AND (
                                    rp1.serial_num < rp2.serial_num
                                    OR (rp1.serial_num = rp2.serial_num AND rp1.id < rp2.id)
                                )
                            )
                            OR
                            (
                                {$tableName}.schedule_type = 'reverse'
                                AND (
                                    rp1.serial_num > rp2.serial_num
                                    OR (rp1.serial_num = rp2.serial_num AND rp1.id > rp2.id)
                                )
                            )
                        )
                    )
                ", array_merge($fromIds, $toIds));
            }
        } else {
            if ($request->filled('trip_from')) {
                $fromIds = \App\Support\GhatPlaceName::resolveGhatIds((string) $data['trip_from']);
                if ($fromIds === []) {
                    $query->whereRaw('0 = 1');
                } else {
                    $query->whereHas('routeProperties', function ($q) use ($fromIds) {
                        $q->whereIn('ghat_id', $fromIds);
                    });
                }
            }

            if ($request->filled('trip_to')) {
                $toIds = \App\Support\GhatPlaceName::resolveGhatIds((string) $data['trip_to']);
                if ($toIds === []) {
                    $query->whereRaw('0 = 1');
                } else {
                    $query->whereHas('routeProperties', function ($q) use ($toIds) {
                        $q->whereIn('ghat_id', $toIds);
                    });
                }
            }
        }

        if ($request->filled('launch_name')) {
            $query->whereHas('launch', function ($q) use ($data) {
                $q->where('name', $data['launch_name']);
            });
        }

        return $query->limit(10)->get();
    }

    /**
     * Load schedules for trip list JSON with relations required by TripService::formatTripList.
     * Preserves the order of $ids (MySQL FIELD()).
     *
     * @param  list<int>  $ids
     */
    public function findSchedulesByIdsForTripList(array $ids): Collection
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        if ($ids === []) {
            return collect();
        }
        $placeholders = implode(',', $ids);

        $query = $this->model->newQuery()
            ->with(['vehicle', 'mappings.cabinType', 'startFrom', 'stopTo', 'route', 'launch'])
            ->whereIn('id', $ids);

        \App\Support\PublicListingVisibility::applyApprovedVehicle($query, 'vehicle');

        return $query
            ->orderByRaw('FIELD(id,'.$placeholders.')')
            ->get();
    }

    /**
     * Accept common aliases from Postman, transport API, and older clients:
     * travel_date → trip_date, vehicle_type → type, from/to_location_id → trip_from/trip_to (ghat names).
     */
    public function normalizeTripSearchRequest($request): void
    {
        $merge = [];

        if ($request->filled('trip_date')) {
            $merge['trip_date'] = date('Y-m-d', strtotime((string) $request->input('trip_date')));
        } else {
            foreach (['travel_date', 'schedule_date', 'date'] as $key) {
                if ($request->filled($key)) {
                    $merge['trip_date'] = date('Y-m-d', strtotime((string) $request->input($key)));
                    break;
                }
            }
        }

        if (! $request->filled('trip_from') && $request->filled('from_location_id')) {
            $name = Ghat::query()->whereKey((int) $request->input('from_location_id'))->value('name');
            if ($name) {
                $merge['trip_from'] = $name;
            }
        }

        if (! $request->filled('trip_to') && $request->filled('to_location_id')) {
            $name = Ghat::query()->whereKey((int) $request->input('to_location_id'))->value('name');
            if ($name) {
                $merge['trip_to'] = $name;
            }
        }

        if (! $request->filled('type') && ! $request->filled('service_type') && $request->filled('vehicle_type')) {
            $merge['type'] = $request->input('vehicle_type');
        }

        if ($merge !== []) {
            $request->merge($merge);
        }
    }

    public function getSupervisorJobs(User $supervisor): LengthAwarePaginator
    {
        $launchIds = $supervisor->vehicles->map(function ($item, $key) {
            return $item->vehicle_id;
        });
        $query = parent::with(['launch.supervisors.user.designation', 'bookingItems'])
            ->whereIn('vehicle_id', $launchIds);
        if (request()->type) {
            switch (request()->type) {
                case 'current' :
                    $query->where('schedule_date', date('Y-m-d'));
                    break;
                case 'complete' :
                    $query->where('schedule_date', '<', date('Y-m-d'));
                    $query->whereHas('bookingItems', function ($query) use ($supervisor) {
                        $query->whereHas('booking', function ($q) use ($supervisor) {
                            $q->where('user_id', $supervisor->id);
                        });
                    })
                        ->orderByDesc('leaving_at');
                    break;
                case 'upcoming':
                    $query->where('schedule_date', '>', date('Y-m-d'));
                    $query->orderBy('leaving_at', 'ASC');
                    break;
            }
        }
        if (request()->date) {
            $query->where('schedule_date', date('Y-m-d', strtotime(request()->date)));
        }
        return $query->paginate(15);;
    }

    public function getListForDropdown(array $params): Collection
    {
        $trip_date = (array_key_exists('trip_date', $params) && $params['trip_date'] != '') ? date('Y-m-d', strtotime($params['trip_date'])) : null;
        $query = parent::with(['launch', 'route']);
        if ($trip_date) {
            $query->where('schedule_date', $trip_date);
        } else {
            $query->where('schedule_date', '>=', date('Y-m-d'));
        }
        if (array_key_exists('vehicle_id', $params) && $params['vehicle_id'] != null) {
            $query->where('vehicle_id', $params['vehicle_id']);
        }

        if (array_key_exists('term', $params) && $params['term']) {
            $query->whereHas('launch', function ($q) use ($params) {
                $q->where('name', 'LIKE', '%' . $params['term'] . '%');
            });
        }

        return $query->get();
    }
}
