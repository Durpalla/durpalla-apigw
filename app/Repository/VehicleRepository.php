<?php


namespace App\Repository;


use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use App\Models\Vehicle;
use App\Repository\Interfaces\VehicleRepositoryInterface;
use App\Models\VehicleSchedule;

class VehicleRepository extends BaseRepository implements VehicleRepositoryInterface
{
    protected $model;
    /**
     * UserRepository constructor.
     *
     * @param Vehicle $model
     */
    public function __construct(Vehicle $model)
    {
        parent::__construct($model);
    }

    public function all() : Collection
    {
        return Cache::remember('vehicles', 120, function() {
            return parent::all();
        });
    }

    public function create(array $data)
    {
        return parent::create($data);
    }

    public function update(array $data, $id)
    {
        return parent::update($data, $id);
    }

    public function getOfficerReports($vehicle_id, $date_from, $date_to, $route_id, $party)
    {
        $query = VehicleSchedule::with([
            'route' => function($q) {
                $q->select('id', 'route_name');
            },
            'bookingItems' => function($q) use($party){
                $q->with([
                    'booking' => function($q) use($party) {
                        $q->with(['officer' => function($q){
                            $q->with('roles')->select('id', 'name', 'designation_id', 'mobile');
                        }]);
                        if($party) {
                            $q->where('booking_party', $party);
                        }
                    },
                    'item' => function($q){
                        $q->select('id', 'cabin_no');
                    },
                    'payment' => function($q){
                        $q->select('booking_id', 'paid_amount', 'dues', 'payment_method');
                    },
                    'collectors' => function($q) {
                        $q->select('id', 'booking_id', 'supervisor_id', 'amount', 'payment_type');
                    },
                    'refunded' => function($q) {
                        $q->select('booking_id', 'user_id', 'refundable_amount');
                    }
                ])
                    ->select('booking_id', 'cabin_id', 'booking_type',
                        'price', 'trip_id', 'trip_date', 'discount',
                        'vat_amount', 'charge_amount', 'status', 'vat_applicable_to',
                        'booking_party', 'discount_type', 'boarding_point', 'charge_type');
            }
        ])
            ->whereHas('bookingItems')
            ->where(['vehicle_id' => $vehicle_id])
            ->whereBetween('schedule_date', [$date_from, $date_to]);
        if($route_id) {
            $query->where('route_id', $route_id);
        }
        return $query->get();
    }

    public function getReport()
    {

    }
}
