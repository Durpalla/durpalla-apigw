<?php

namespace App\Repositories\Hotel;

interface BookingHotelItemRepositoryInterface
{
    public function getAll();
    public function find($id);
    public function create(array $data);
    public function update(array $data, $id);
    public function delete($id);
    public function getByBooking($bookingId);
    public function getByHotel($hotelId);
    public function getByDateRange($checkIn, $checkOut);
}
