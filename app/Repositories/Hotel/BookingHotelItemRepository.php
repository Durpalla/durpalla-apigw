<?php

namespace App\Repositories\Hotel;

use App\Repository\BaseRepository;
use App\Models\BookingHotelItem;

class BookingHotelItemRepository extends BaseRepository implements BookingHotelItemRepositoryInterface
{
    public function __construct(BookingHotelItem $model)
    {
        parent::__construct($model);
        $this->model = $model;
    }

    public function getAll()
    {
        return $this->model->with(['booking', 'hotel', 'room', 'roomType', 'ratePlan'])->latest()->get();
    }

    public function getByBooking($bookingId)
    {
        return $this->model->where('booking_id', $bookingId)
            ->with(['hotel', 'room', 'roomType', 'ratePlan'])
            ->get();
    }

    public function getByHotel($hotelId)
    {
        return $this->model->where('hotel_id', $hotelId)
            ->with(['booking', 'room', 'roomType', 'ratePlan'])
            ->latest()
            ->get();
    }

    public function getByDateRange($checkIn, $checkOut)
    {
        return $this->model->where(function ($query) use ($checkIn, $checkOut) {
            $query->whereBetween('check_in_date', [$checkIn, $checkOut])
                ->orWhereBetween('check_out_date', [$checkIn, $checkOut])
                ->orWhere(function ($q) use ($checkIn, $checkOut) {
                    $q->where('check_in_date', '<=', $checkIn)
                      ->where('check_out_date', '>=', $checkOut);
                });
        })
        ->with(['booking', 'hotel', 'room', 'roomType', 'ratePlan'])
        ->get();
    }
}
