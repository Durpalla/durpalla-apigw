<?php


namespace App\Repository;


use App\Constants\AppConst;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use App\Models\Booking;
use App\Repository\Interfaces\BookingRepositoryInterface;

class BookingRepository extends BaseRepository implements BookingRepositoryInterface
{
    protected $model;

    /**
     * UserRepository constructor.
     *
     * @param Booking $model
     */
    public function __construct(Booking $model)
    {
        parent::__construct($model);
    }

    public function all(): Collection
    {
        return $this->model->all();
    }

    public function find($booking_id)
    {
        return parent::find($booking_id);
    }

    public function create(array $data)
    {
        return parent::create($data);
    }

    public function update(array $data, $id)
    {
        return parent::update($data, $id);
    }

    public function getDataForReport(array $params)
    {
        $query = parent::with([
            'bookingItems' => function ($query) {
                $query->with(['item' => function ($q) {
                    $q->with(['cabinType' => function ($q) {
                        $q->select('id', 'name', 'is_ac', 'letter');
                    }]);
                    $q->select('id', 'type_id', 'cabin_no');
                }, 'deck.departureFrom', 'deck.departureTo']);
                $query->select('booking_id', 'cabin_id', 'booking_type', 'status', 'price', 'discount', 'discount_type', 'vat_amount', 'charge_amount', 'incentive', 'incentive_type', 'charge_type');
                $query->where('status', 1);
            },
            'payment' => function ($query) {
                $query->select('payments.id', 'payments.booking_id', 'payments.paid_amount', 'payments.dues');
            },
            'collections' => function ($query) use ($params) {
                $query->with(['supervisor' => function ($query) use ($params) {
                    $query->select('name', 'id', 'designation_id');
                }]);
                $query->select('booking_id', 'supervisor_id', 'amount', 'payment_type');
                $query->where('amount', '>', 0);
                if (array_key_exists('officer_id', $params) && $params['officer_id']) {
                    $query->where('supervisor_id', $params['officer_id']);
                }
                $query->orderBy('created_at', 'ASC');
            },
            'cancellations' => function ($query) use ($params) {
                $query->select('booking_id', 'total_refundable', 'charge_refundable', 'vat_refundable');
                $query->where('status', 1);
            }
        ])
            ->select('id', 'total_payable', 'charge_total', 'vat_total', 'booking_party')
            ->whereHas('bookingItems', function ($q) use ($params) {
                if ($params['trip_id']) {
                    $q->where('trip_id', $params['trip_id']);
                }
                if (array_key_exists('status', $params) && $params['status']) {
                    $q->where('status', $params['status']);
                }
            });
        if (array_key_exists('officer_id', $params) && $params['officer_id']) {
            $query->where('user_id', $params['officer_id']);
        }
        return $query->get();
    }

    public function getLaunchBookingReport($params)
    {
        $query = parent::with([
            'officer' => function ($q) {
                $q->with('roles');
                $q->select('id', 'name', 'mobile');
            },
            'bookingItems' => function ($query) use ($params) {
                $query->with(['item' => function ($q) {
                    $q->with(['cabinType' => function ($q) {
                        $q->select('id', 'name', 'is_ac', 'letter');
                    }]);
                    $q->select('id', 'type_id', 'cabin_no');
                }, 'deck.departureFrom', 'deck.departureTo']);
                $query->select('booking_id', 'cabin_id', 'booking_type', 'status', 'price', 'discount', 'discount_type', 'vat_amount', 'charge_amount', 'incentive', 'incentive_type', 'charge_type');
                $query->where(['status' => 1, 'vehicle_id' => $params['vehicle_id']]);
            },
            'payment' => function ($query) {
                $query->select('payments.id', 'payments.booking_id', 'payments.paid_amount', 'payments.dues');
            },
            'collections' => function ($query) use ($params) {
                $query->with(['supervisor' => function ($query) use ($params) {
                    $query->select('name', 'id', 'designation_id');
                }]);
                $query->select('booking_id', 'supervisor_id', 'amount', 'payment_type');
                $query->where('amount', '>', 0);
                if (array_key_exists('officer_id', $params) && $params['officer_id']) {
                    $query->where('supervisor_id', $params['officer_id']);
                }
                $query->orderBy('created_at', 'ASC');
            },
            'cancellations' => function ($query) use ($params) {
                $query->select('booking_id', 'total_refundable', 'charge_refundable', 'vat_refundable');
                $query->where('status', 1);
            }
        ])
            ->select('id', 'customer_id', 'user_id', 'total_payable', 'charge_total', 'vat_total', 'booking_party')
            ->whereHas('bookingItems', function ($q) use ($params) {
                if ($params['vehicle_id']) {
                    $q->where('vehicle_id', $params['vehicle_id']);
                }
                if (array_key_exists('status', $params) && $params['status']) {
                    $q->where('status', $params['status']);
                }
            });
        if (array_key_exists('officer_id', $params) && $params['officer_id']) {
            $query->where('user_id', $params['officer_id']);
        }
        return $query->get();
    }

    public function getDailyBookings($booking_date): Collection
    {
        return Cache::remember('daily_booking_' . $booking_date, 120, function () use ($booking_date) {
            return new Collection(parent::with([
                'bookingItems' => function ($query) {
                    $query->with(['item.cabinType', 'trip.route', 'launch'])->where('status', AppConst::BOOKING_ITEM_ACTIVE);
                },
                'payment',
                'customer',
                'officer'
            ])->has('bookingItems', '>', 0)->where('booking_date', $booking_date)->whereIn('status', [AppConst::BOOKING_COMPLETE, AppConst::BOOKING_ADVANCE])->get());
        });
    }

    public function getOfficerBookings($officerID, $params) : Collection
    {
        $booking_date = (array_key_exists('date', $params)) ? date('Y-m-d', strtotime($params['date'])) : date('Y-m-d');
        return new Collection(parent::with([
            'bookingItems'
        ])
            ->has('bookingItems', '>', 0)
            ->where(['user_id' => $officerID, 'booking_date' => $booking_date])
            ->whereIn('status', [AppConst::BOOKING_COMPLETE, AppConst::BOOKING_ADVANCE, AppConst::BOOKING_CANCELLED])->get());
    }
}
