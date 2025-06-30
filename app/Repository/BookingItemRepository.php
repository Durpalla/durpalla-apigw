<?php


namespace App\Repository;


use App\Constants\AppConst;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use App\Models\BookingItem;
use App\Repository\Interfaces\BookingItemRepositoryInterface;

class BookingItemRepository extends BaseRepository implements BookingItemRepositoryInterface
{
    public function __construct(BookingItem $model)
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

    public function getTripItems($tripID) : Collection
    {
        return Cache::remember('daily_trip_booking_' . $tripID, 120, function () use ($tripID) {
            return new Collection(parent::with([
                'booking',
                'customer'
            ])->where('trip_id', $tripID)->where('status', AppConst::BOOKING_ITEM_ACTIVE)->get());
        });
    }
}
