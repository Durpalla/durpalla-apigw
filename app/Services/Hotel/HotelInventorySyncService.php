<?php

namespace App\Services\Hotel;

use Carbon\Carbon;
use App\Models\HotelRoom;
use App\Models\HotelRoomInventory;

class HotelInventorySyncService
{
    /**
     * Sellable capacity for a hotel room type (sum of active room inventories).
     */
    public function capacityForRoomType(int $hotelId, int $roomTypeId): int
    {
        $capacity = (int) HotelRoom::query()
            ->where('hotel_id', $hotelId)
            ->where('room_type_id', $roomTypeId)
            ->where('status', 1)
            ->sum('total_rooms');

        return max(0, $capacity);
    }

    /**
     * Create missing inventory nights from physical capacity (does not overwrite existing rows).
     *
     * @param  array<int, string>  $dates  Y-m-d nights
     */
    public function ensureNights(int $hotelId, int $roomTypeId, array $dates): void
    {
        if ($dates === []) {
            return;
        }

        $capacity = $this->capacityForRoomType($hotelId, $roomTypeId);
        if ($capacity < 1) {
            return;
        }

        $existing = HotelRoomInventory::query()
            ->where('hotel_id', $hotelId)
            ->where('room_type_id', $roomTypeId)
            ->whereIn('date', $dates)
            ->pluck('date')
            ->map(fn ($date) => Carbon::parse($date)->format('Y-m-d'))
            ->all();

        $existingLookup = array_fill_keys($existing, true);

        foreach ($dates as $date) {
            if (isset($existingLookup[$date])) {
                continue;
            }

            HotelRoomInventory::query()->create([
                'hotel_id' => $hotelId,
                'room_type_id' => $roomTypeId,
                'date' => $date,
                'total_rooms' => $capacity,
                'booked_rooms' => 0,
                'held_rooms' => 0,
                'available_rooms' => $capacity,
            ]);
        }
    }

    /**
     * Keep a rolling horizon of nights in sync with current room quantity.
     * Existing booked counts are preserved; available is recomputed.
     */
    public function syncHorizon(int $hotelId, int $roomTypeId, ?int $days = null): void
    {
        $days = $days ?? (int) config('hotel.inventory_horizon_days', 365);
        $days = max(1, min(730, $days));
        $capacity = $this->capacityForRoomType($hotelId, $roomTypeId);

        $start = Carbon::today()->startOfDay();
        $endExclusive = $start->copy()->addDays($days);

        $dates = [];
        $cursor = $start->copy();
        while ($cursor->lt($endExclusive)) {
            $dates[] = $cursor->format('Y-m-d');
            $cursor->addDay();
        }

        if ($capacity < 1) {
            // Still ensure structure is not required when capacity is zero.
            return;
        }

        $this->ensureNights($hotelId, $roomTypeId, $dates);

        $rows = HotelRoomInventory::query()
            ->where('hotel_id', $hotelId)
            ->where('room_type_id', $roomTypeId)
            ->whereIn('date', $dates)
            ->get();

        foreach ($rows as $row) {
            $booked = max(0, (int) $row->booked_rooms);
            $row->total_rooms = $capacity;
            $row->available_rooms = max(0, $capacity - $booked);
            $row->save();
        }
    }

    /**
     * @return array<int, string>
     */
    public function stayDates(Carbon $checkIn, Carbon $checkOut): array
    {
        $dates = [];
        $current = $checkIn->copy()->startOfDay();
        $end = $checkOut->copy()->startOfDay();
        while ($current->lt($end)) {
            $dates[] = $current->format('Y-m-d');
            $current->addDay();
        }

        return $dates;
    }
}
