<?php

namespace App\Services\Hotel;

use App\Models\HotelStopSale;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;

class HotelStopSaleService
{
    /**
     * Stay nights [checkIn, checkOut) overlap a stop window [starts_on, ends_on] inclusive.
     */
    public function blocksStay(int $hotelId, ?int $roomTypeId, $checkIn, $checkOut): bool
    {
        return $this->blockingWindow($hotelId, $roomTypeId, $checkIn, $checkOut) !== null;
    }

    public function blockingWindow(int $hotelId, ?int $roomTypeId, $checkIn, $checkOut): ?HotelStopSale
    {
        if (! Schema::hasTable('hotel_stop_sales')) {
            return null;
        }

        $checkIn = Carbon::parse($checkIn)->startOfDay();
        $checkOut = Carbon::parse($checkOut)->startOfDay();
        if (! $checkOut->gt($checkIn)) {
            return null;
        }

        return HotelStopSale::query()
            ->where('hotel_id', $hotelId)
            ->where(function ($q) use ($roomTypeId) {
                $q->whereNull('room_type_id');
                if ($roomTypeId) {
                    $q->orWhere('room_type_id', $roomTypeId);
                }
            })
            ->whereDate('starts_on', '<', $checkOut->toDateString())
            ->whereDate('ends_on', '>=', $checkIn->toDateString())
            ->orderBy('starts_on')
            ->first();
    }

    public function assertBookable(int $hotelId, ?int $roomTypeId, $checkIn, $checkOut): void
    {
        $window = $this->blockingWindow($hotelId, $roomTypeId, $checkIn, $checkOut);
        if (! $window) {
            return;
        }

        $scope = $window->room_type_id ? 'this room type' : 'this hotel';
        $from = Carbon::parse($window->starts_on)->toDateString();
        $to = Carbon::parse($window->ends_on)->toDateString();
        $reason = $window->reason ? ' ('.$window->reason.')' : '';

        throw new \RuntimeException("Not selling {$scope} from {$from} to {$to}{$reason}.");
    }
}
