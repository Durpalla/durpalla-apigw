<?php

namespace App\Services\Hotel\Suppliers;

use App\Services\Hotel\Contracts\HotelSupplierInterface;
use App\Services\Hotel\DTO\HotelSearchRequestDTO;
use App\Models\Hotel;
use App\Models\HotelRoom;
use App\Models\HotelRoomInventory;
use App\Models\RoomType;
use App\Models\RoomRatePlan;
use App\Services\Hotel\HotelStopSaleService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class LocalHotelSupplier implements HotelSupplierInterface
{
    public function search(HotelSearchRequestDTO $request): array
    {
        $checkIn = Carbon::parse($request->checkIn);
        $checkOut = Carbon::parse($request->checkOut);
        $nights = $checkIn->diffInDays($checkOut);

        $hotels = Hotel::query()
            ->where('status', 1)
            ->where(function ($q) {
                $q->where('source', 'local')->orWhereNull('source');
            })
            ->where(function ($q) {
                // Total shutdown: hide inactive merchants' properties from search.
                $q->whereNull('merchant_id')
                    ->orWhereHas('merchant', fn ($m) => $m->where('status', 1));
            })
            // Direct hotel search takes precedence: by id, then by name, else fall back to city.
            ->when($request->hotelId, fn ($q) => $q->where('id', $request->hotelId))
            ->when(! $request->hotelId && $request->hotelName, fn ($q) => $q->where('name', 'like', '%' . $request->hotelName . '%'))
            ->when(! $request->hotelId && ! $request->hotelName, fn ($q) => $q->where('city_id', $request->cityId))
            ->with(['activeRooms.roomType', 'facilities', 'images'])
            ->get();

        $offers = [];

        foreach ($hotels as $hotel) {
            // One offer per (room_type × rate_plan). Multiple hotel_rooms rows can share
            // the same room_type_id (physical capacity); iterating each row previously
            // duplicated the same room type in Quick Booking / search results.
            $roomsByType = $hotel->activeRooms
                ->filter(fn ($room) => ! empty($room->room_type_id))
                ->groupBy('room_type_id');

            foreach ($roomsByType as $roomTypeId => $roomsOfType) {
                /** @var HotelRoom $room Representative row for pricing / book_token */
                $room = $roomsOfType->sortBy('id')->first();

                // Bookable rooms for the whole stay (limited by the tightest night).
                // Availability is tracked per room type, so all rate plans share this pool.
                $availableRooms = $this->availableUnits($hotel->id, (int) $roomTypeId, $checkIn, $checkOut);

                if ($availableRooms <= 0) {
                    continue;
                }

                $ratePlans = RoomRatePlan::where('room_type_id', $roomTypeId)
                    ->where('status', 1)
                    ->get();

                foreach ($ratePlans as $ratePlan) {
                    $price = $this->resolvePriceForDates($room, $checkIn, $checkOut);
                    $totalPrice = $price * $nights;

                    $offers[] = [
                        'hotel_id' => $hotel->id,
                        'hotel_name' => $hotel->name,
                        'room_type_id' => (int) $roomTypeId,
                        'room_type' => $room->roomType?->displayLabel() ?? ($room->roomType->name ?? null),
                        'room_category' => $room->roomType->category ?? 'room',
                        'rate_plan_id' => $ratePlan->id,
                        'rate_plan' => $ratePlan->name,
                        'price' => $totalPrice,
                        'unit_price' => $price,
                        'available_rooms' => $availableRooms,
                        'currency' => 'BDT',
                        'cancellation_policy' => $ratePlan->cancellation_policy,
                        'supplier' => 'local',
                        'book_token' => encrypt([
                            'supplier' => 'local',
                            'hotel_id' => $hotel->id,
                            'room_id' => $room->id,
                            'room_type_id' => (int) $roomTypeId,
                            'rate_plan_id' => $ratePlan->id,
                        ]),
                    ];
                }
            }
        }

        return $offers;
    }

    public function getAvailability(array $criteria): array
    {
        $checkIn = Carbon::parse($criteria['check_in']);
        $checkOut = Carbon::parse($criteria['check_out']);

        $available = $this->checkAvailability(
            $criteria['hotel_id'],
            $criteria['room_type_id'],
            $checkIn,
            $checkOut
        );

        return [
            'available' => $available,
            'supplier' => 'local',
        ];
    }

    public function recheckRate(string $rateKey): array
    {
        // For local hotels, rate is always valid (no expiry)
        $data = decrypt($rateKey);
        return [
            'valid' => true,
            'price' => $data['price'] ?? null,
            'supplier' => 'local',
        ];
    }

    public function book(array $payload): array
    {
        // Local booking logic handled by HotelBookingService
        return [
            'success' => true,
            'status' => 'confirmed',
            'supplier_booking_reference' => null,
            'raw' => [],
        ];
    }

    public function cancel(string $supplierBookingReference, array $options = []): array
    {
        // Local cancellation handled by booking service
        return [
            'success' => true,
            'status' => 'cancelled',
            'raw' => [],
        ];
    }

    public function getBookingStatus(string $supplierBookingReference): array
    {
        return [
            'success' => true,
            'status' => 'confirmed',
            'raw' => [],
        ];
    }

    public function getSupplierCode(): string
    {
        return 'local';
    }

    /**
     * Whether the room type is bookable for the full stay (>= 1 room on every night).
     */
    protected function checkAvailability($hotelId, $roomTypeId, Carbon $checkIn, Carbon $checkOut): bool
    {
        return $this->availableUnits($hotelId, $roomTypeId, $checkIn, $checkOut) > 0;
    }

    /**
     * Number of rooms of this type bookable for the ENTIRE stay.
     *
     * Availability is stored per (hotel, room_type, date), so the sellable count for a
     * multi-night stay is the minimum available_rooms across every night (the bottleneck
     * night). When inventory is not being tracked for this hotel/room type at all (no rows
     * for the range) we fall back to the physical room capacity so such hotels remain
     * bookable and still expose a sane cap. Partial tracking (some nights missing) counts a
     * missing night as 0, i.e. not bookable for the whole stay.
     */
    public function availableUnits($hotelId, $roomTypeId, Carbon $checkIn, Carbon $checkOut): int
    {
        if (app(HotelStopSaleService::class)->blocksStay((int) $hotelId, $roomTypeId ? (int) $roomTypeId : null, $checkIn, $checkOut)) {
            return 0;
        }

        $dates = $this->stayDates($checkIn, $checkOut);
        if (empty($dates)) {
            return 0;
        }

        $rows = HotelRoomInventory::where('hotel_id', $hotelId)
            ->where('room_type_id', $roomTypeId)
            ->whereIn('date', $dates)
            ->get()
            ->keyBy(fn ($row) => Carbon::parse($row->date)->format('Y-m-d'));

        // Inventory not tracked for this room type in the range → physical capacity.
        if ($rows->isEmpty()) {
            return $this->fallbackCapacity($hotelId, $roomTypeId);
        }

        $fallback = $this->fallbackCapacity($hotelId, $roomTypeId);
        $min = null;
        foreach ($dates as $date) {
            $inventory = $rows->get($date);
            // Missing nights (not yet seeded) use physical capacity so merchants need no manual calendar.
            $available = $inventory
                ? max(0, (int) $inventory->available_rooms)
                : $fallback;
            $min = is_null($min) ? $available : min($min, $available);
            if ($min === 0) {
                break;
            }
        }

        return (int) ($min ?? 0);
    }

    /**
     * Physical room capacity for a room type when no inventory rows exist (untracked).
     */
    protected function fallbackCapacity($hotelId, $roomTypeId): int
    {
        $capacity = (int) HotelRoom::where('hotel_id', $hotelId)
            ->where('room_type_id', $roomTypeId)
            ->where('status', 1)
            ->sum('total_rooms');

        return $capacity > 0
            ? $capacity
            : (int) config('hotel.default_inventory_units_per_night', 10);
    }

    /**
     * List of night dates in [check_in, check_out).
     */
    protected function stayDates(Carbon $checkIn, Carbon $checkOut): array
    {
        $dates = [];
        $current = $checkIn->copy();
        while ($current->lt($checkOut)) {
            $dates[] = $current->format('Y-m-d');
            $current->addDay();
        }

        return $dates;
    }

    /**
     * Resolve per-night price for a room based on stay dates (peak vs off-peak).
     * Peak months (e.g. Nov, Dec, Jan) use peak_price; otherwise off_peak_price.
     * Falls back to base_price when specific price is not set.
     */
    protected function resolvePriceForDates($room, Carbon $checkIn, Carbon $checkOut): float
    {
        $peakMonths = config('hotel.peak_months', [11, 12, 1]); // Nov, Dec, Jan
        $current = $checkIn->copy();
        $isPeak = false;
        while ($current->lt($checkOut)) {
            if (in_array((int) $current->month, $peakMonths, true)) {
                $isPeak = true;
                break;
            }
            $current->addDay();
        }

        if ($isPeak && $room->peak_price !== null) {
            return (float) $room->peak_price;
        }
        if (!$isPeak && $room->off_peak_price !== null) {
            return (float) $room->off_peak_price;
        }
        return (float) ($room->base_price ?? 0);
    }
}
