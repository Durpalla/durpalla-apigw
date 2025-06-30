<?php


namespace App\Repository;


use App\Constants\AppConst;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use App\Models\BookingCancellation;
use App\Repository\Interfaces\CancellationRepositoryInterface;

class CancellationRepository extends BaseRepository implements CancellationRepositoryInterface
{
    protected $model;
    /**
     * UserRepository constructor.
     *
     * @param BookingCancellation $model
     */
    public function __construct(BookingCancellation $model)
    {
        parent::__construct($model);
    }

    public function all() : Collection
    {
        return parent::all();
    }

    public function create(array $data)
    {
        return parent::create($data);
    }

    public function getOfficerCancellations( $officerID)
    {
        Cache::forget('cancellations_' . $officerID);
        return Cache::remember('cancellations_' . $officerID, 3600, function () use($officerID){
            return parent::with([
                'booking' => function($q) {
                    $q->select('id', 'booking_date', 'total_payable', 'created_at');
                },
                'customer' => function($q) {
                    $q->select('id', 'name', 'mobile');
                },
                'cancellationItems' => function($q) {
                    $q->with(['bookingItem' => function($q) {
                        $q->with('item.cabinType');
                        $q->select('id', 'cabin_id', 'booking_type', 'trip_date', 'vat_amount', 'price', 'charge_amount', 'discount', 'discount_type');
                    }]);
                }
            ])
                ->where('user_id', $officerID)
                ->orderByDesc('created_at')
                ->get();
        });
    }

    public function getSupervisorCancellations($supervisorId, $tripId)
    {
        return $this->model->with(['cancellationItems.bookingItem'])
            ->whereHas('cancellationItems', function($q) use($supervisorId, $tripId) {
                $q->whereHas('bookingItem', function($q) use($tripId) {
                    $q->where('trip_id', $tripId);
                });
            })
            ->where('user_id', $supervisorId)
            ->where('status', AppConst::CANCELLATION_REFUNDED)
            ->get();
    }

    public function get(int $id)
    {
        return $this->model->findOrFail($id);
    }
}
