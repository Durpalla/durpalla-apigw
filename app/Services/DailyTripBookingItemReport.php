<?php


namespace App\Services;


use App\Models\VehicleSchedule;
use App\Repository\Interfaces\BookingItemRepositoryInterface;

class DailyTripBookingItemReport
{
    private $bookingItem;
    private $calculation;
    public function __construct(BookingItemRepositoryInterface $bookingItemRepository, CalculationService $calculationService)
    {
        $this->bookingItem = $bookingItemRepository;
        $this->calculation = $calculationService;
    }

    public function getTripReport($tripID, $type = 'cabin')
    {
        $trip = VehicleSchedule::findOrFail($tripID);
        $items = $this->bookingItem->getTripItems($tripID);
        if(in_array($type, ['cabin', 'seat', 'deck'])) {
            $items = $items->where('booking_type', $type);
        }
        return $items->map(function($item, $key) use($trip) {
            $passenger = json_decode($item->passenger);
            $routes = explode('-', $trip->route->route_name);
            $route_name = ($trip->route->schedule_type === 'reverse') ? $routes[1] . ' - ' . $routes[0] : $trip->route->route_name;
            return [
                'invoice' => $item->booking_id,
                'customer_id' => $item->customer_id,
                'customer_info' => $item->customer->name . ' - ' . $item->customer->mobile,
                'customer_mobile' => $item->customer->mobile,
                'booking_date' => $item->booking_date,
                'journey_date' => $trip->leaving_at,
                'route' => $route_name,
                'launch' => $trip->launch->name,
                'type' => $item->booking_type,
                'fare' => $item->price,
                'cabin_no' => $item->item->cabin_no,
                'cabin_letter' => $item->item->cabinType->letter,
                'passenger' => ($passenger) ? $passenger->person : 1,
                'charge' => $this->calculation->calculateItemCharge($item->toArray()),
                'vat' => $this->calculation->calculateItemVat($item->toArray()),
                'discount' => $this->calculation->calculateItemDiscount($item->toArray()),
                'total' => $this->calculation->calculateItemTotal($item->toArray()),
                'party' => $item->booking->booking_party
            ];
        });
    }
}
