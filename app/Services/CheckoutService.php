<?php


namespace App\Services;


use App\Repository\Interfaces\BookingRepositoryInterface;

class CheckoutService
{
    private $booking;

    public function __construct(BookingRepositoryInterface $bookingRepository)
    {
        $this->booking = $bookingRepository;
    }

    public function getOrder(int $orderID)
    {
        return $this->booking->with(['bookingItems', 'officer'])->find($orderID);
    }
}
