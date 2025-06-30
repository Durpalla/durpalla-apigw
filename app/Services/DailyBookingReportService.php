<?php


namespace App\Services;


use App\Constants\AppConst;
use App\Models\VehicleSchedule;
use App\Repository\Interfaces\BookingItemRepositoryInterface;
use App\Repository\Interfaces\BookingRepositoryInterface;

class DailyBookingReportService
{
    private $booking;
    private $bookingItem;
    private $default_date;
    public function __construct( BookingRepositoryInterface $bookingRepository, BookingItemRepositoryInterface $bookingItemRepository)
    {
        $this->bookingItem = $bookingItemRepository;
        $this->booking = $bookingRepository;
        $this->default_date = date('Y-m-d');
    }

    public function get($booking_date, $status = '', $limit, $start)
    {
        $booking_date = ($booking_date) ? $booking_date : $this->default_date;
        $bookings = $this->booking->getDailyBookings($booking_date);
        if($status) {
            $bookings = $bookings->where('status', $status);
        }
        $per_page = $limit - $start;
        $page = $start/$per_page;
        $total = $bookings->count();
        if ($limit < 0) {
            $per_page = $total;
            $page = 1;
        }
        return $bookings->forPage($page, $per_page)->map(function($item, $key) {
            $bookingItems = $item->bookingItems->map(function($item, $key) {
                $passenger = json_decode($item->passenger);
                $passengers = ($passenger) ? $passenger->person : 1;
                $route = explode('-', $item->trip->route->route_name);
                return [
                    'date' => $item->trip_date,
                    'route' => ($item->trip && $item->trip->schedule_type == 'reverse') ? trim($route[1]) . ' - ' . $route[0] : $item->trip->route->route_name,
                    'launch' => $item->launch->name,
                    'vehicle' => $item->launch->name,
                    'type' => $item->booking_type,
                    'item_no' => ($item->item) ? $item->item->cabin_no : '',
                    'passenger' => $passengers,
                    'passenger_info' => ($passenger && $passenger->type == 'other') ? ucwords($passenger->name) . ' - ' . $passenger->mobile : '',
                    'letter' => ($item->booking_type != 'deck' && $item->item['cabinType']) ? $item->item['cabinType']['letter'] : ''
                ];
            });
            return [
                'invoice' => $item->id,
                'customer_id' => $item->customer_id,
                'customer_info' => $item->customer->name . ' - ' . $item->customer->mobile,
                'customer_mobile' => $item->customer->mobile,
                'booking_date' => $item->booking_date,
                'journey_dates' => $bookingItems->unique('date')->implode('date', ','),
                'routes' => $bookingItems->unique('route')->implode('route', ','),
                'vehicles' => $bookingItems->unique('launch')->implode('launch', ','),
                'cabins' => $bookingItems->where('type', 'cabin')->implode('item_no', ','),
                'seats' => $bookingItems->where('type', 'seat')->implode('item_no', ','),
                'decks' => $bookingItems->where('type', 'deck')->sum('passenger'),
                'total_items' => $bookingItems->count(),
                'total_amount' => number_format($item->total_payable, 2),
                'paid_amount' => number_format($item->payment['paid_amount'], 2),
                'due_amount' => number_format($item->payment['dues'], 2),
                'service_charge' => number_format($item->charge_total, 2),
                'gateway_charge' => ($item->status == AppConst::BOOKING_COMPLETE) ? number_format($item->total_payable - $item->payment['store_amount'], 2) : 0,
                'other_passenger' => $bookingItems->unique('passenger_info')->implode('passenger_info', ','),
                'platform' => $item->platform,
                'party' => $item->booking_party,
                'status' => $item->status
            ];
        });
    }

    public function getLaunchReport($vehicle_id, $booking_date, $type)
    {
        $trip = VehicleSchedule::where(['vehicle_id' =>  $vehicle_id, 'schedule_date' => $booking_date, 'status' => AppConst::SCHEDULE_ACTIVE])->first();

        if($trip) {
            $items = $this->bookingItem->getTripItems($trip->id);

            if(in_array($type, ['cabin', 'seat', 'deck'])) {
                $items = $items->where('booking_type', $type);
            }
            return $items->map(function($item, $key) use($trip) {
                $passenger = json_decode($item->passenger);
                return [
                    'invoice' => $item->booking_id,
                    'customer_id' => $item->customer_id,
                    'customer_info' => $item->customer->name . ' - ' . $item->customer->mobile,
                    'customer_mobile' => $item->customer->mobile,
                    'booking_date' => $item->booking_date,
                    'journey_date' => $trip->leaving_at,
                    'route' => $trip->route->route_name,
                    'vehicle' => $trip->launch->name,
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
        } else {
            throw new \Exception("No schedule found on selected date");
        }
    }
}
