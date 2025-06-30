<?php

namespace App\Repository;

use App\Constants\AppConst;
use App\Models\VehicleSchedule;
use App\Repository\Interfaces\ScheduleRepositoryInterface;
use Illuminate\Support\Collection;
use App\Models\User;

class ScheduleRepository extends BaseRepository implements ScheduleRepositoryInterface
{
    protected $model;
    /**
     * UserRepository constructor.
     *
     * @param VehicleSchedule $model
     */
    public function __construct(VehicleSchedule $model)
    {
        parent::__construct($model);
    }

    /**
     * @return Collection
     */
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

    public function searchTrip($trip_date, $return_date, $data)
    {
        $query = $this->model->with(['launch', 'route', 'startingPoint.ghat', 'endingPoint.ghat', 'boardingVias.ghat', 'cabinMappings', 'seatMappings', 'locks', 'bookingItems', 'routeProperties.ghat'])
            ->withCount('cabins')
            ->where('status', AppConst::SCHEDULE_ACTIVE);

        if(array_key_exists('trip_id', $data) && $data['trip_id']) {
            $query->where('id', $data['trip_id']);
        } elseif(array_key_exists('vehicle_id', $data) && $data['vehicle_id']) {
            $query->where('vehicle_id', $data['vehicle_id'])
                ->where('leaving_at', '>=', date('Y-m-d H:i:s'))
                ->orderBy('leaving_at', 'ASC');
        } else {
            if ($return_date !== '') {
                $query->whereIn('schedule_date', [$trip_date, $return_date]);
            } else {
                $query->where('schedule_date', $trip_date);
            }
        }

        if(array_key_exists('service_type', $data)) {
            $query->whereHas('launch', function($q) use($data) {
                $q->where('vehicle_type', $data['service_type']);
            });
        }

        if (array_key_exists('trip_from', $data) && !empty($data['trip_from'])) {
            $query->whereHas('routeProperties', function ($q) use ($data) {
                $q->whereHas('ghat', function ($q) use ($data) {
                    $q->where(['name' => $data['trip_from']]);
                });
            });
        }

        if (array_key_exists('trip_to', $data) && !empty($data['trip_to'])) {
            $query->whereHas('routeProperties', function ($q) use ($data) {
                $q->whereHas('ghat', function ($q) use ($data) {
                    $q->where(['name' => $data['trip_to']]);
                });
            });
        }

        if (array_key_exists('launch_name', $data) && !empty($data['launch_name'])) {
            $query->whereHas('launch', function ($q) use ($data) {
                $q->where('name', $data['launch_name']);
            });
        }

        return $query->limit(10)->get();
    }

    public function getSupervisorJobs(User $supervisor)
    {
        $launchIds = $supervisor->vehicles->map(function($item, $key) {
            return $item->vehicle_id;
        });
        $query = parent::with(['launch.supervisors.user.designation', 'bookingItems'])
            ->whereIn('vehicle_id', $launchIds);
        if(request()->type ) {
            switch (request()->type) {
                case 'current' :
                    $query->where('schedule_date', date('Y-m-d'));
                    break;
                case 'complete' :
                    $query->where('schedule_date', '<', date('Y-m-d'));
                    $query->whereHas('bookingItems', function($query) use($supervisor) {
                        $query->whereHas('booking', function($q) use($supervisor) {
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
        if(request()->date ) {
            $query->where('schedule_date', date('Y-m-d', strtotime(request()->date)));
        }
        return $query->paginate(15);;
    }

    public function getListForDropdown(array $params)
    {
        $trip_date = (array_key_exists('trip_date', $params) && $params['trip_date'] != '') ? date('Y-m-d', strtotime($params['trip_date'])) : null;
        $query = parent::with(['launch', 'route']);
        if($trip_date) {
            $query->where('schedule_date', $trip_date);
        } else {
            $query->where('schedule_date', '>=', date('Y-m-d'));
        }
        if(array_key_exists('vehicle_id', $params) && $params['vehicle_id'] != null) {
            $query->where('vehicle_id', $params['vehicle_id']);
        }

        if(array_key_exists('term', $params) && $params['term']) {
            $query->whereHas('launch', function($q) use($params) {
                $q->where('name', 'LIKE', '%' . $params['term'] . '%');
            });
        }

        return $query->get();
    }
}
