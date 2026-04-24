<?php

namespace App\Services\Hotel;

use App\Models\HotelInventory;
use App\Models\HotelRoomType;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\DB;

final class HotelInventoryService
{
    /**
     * @return list<string> Y-m-d dates in [check_in, check_out)
     */
    public static function nightDates(Carbon $checkIn, Carbon $checkOut): array
    {
        $out = [];
        $period = CarbonPeriod::create($checkIn->toDateString(), $checkOut->copy()->subDay()->toDateString());
        foreach ($period as $d) {
            $out[] = $d->toDateString();
        }

        return $out;
    }

    /**
     * @throws \RuntimeException when not enough units for every night
     */
    public function assertAvailability(
        HotelRoomType $roomType,
        Carbon $checkIn,
        Carbon $checkOut,
        int $unitsRequested = 1,
    ): void {
        $dates = self::nightDates($checkIn, $checkOut);
        if ($dates === []) {
            throw new \InvalidArgumentException('Invalid stay range');
        }

        foreach ($dates as $date) {
            $row = HotelInventory::query()
                ->where('hotel_room_type_id', $roomType->id)
                ->whereDate('night_date', $date)
                ->first();
            if (! $row) {
                throw new \RuntimeException('No inventory for '.$date);
            }
            $avail = (int) $row->units_total - (int) $row->units_sold - (int) $row->units_held;
            if ($avail < $unitsRequested) {
                throw new \RuntimeException('Not enough rooms for '.$date);
            }
        }
    }

    /**
     * Lock inventory rows and increment units_held (call inside transaction with lockForUpdate).
     */
    public function applyHold(HotelRoomType $roomType, Carbon $checkIn, Carbon $checkOut, int $units = 1): void
    {
        foreach (self::nightDates($checkIn, $checkOut) as $date) {
            $row = HotelInventory::query()
                ->where('hotel_room_type_id', $roomType->id)
                ->whereDate('night_date', $date)
                ->lockForUpdate()
                ->first();
            if (! $row) {
                throw new \RuntimeException('No inventory for '.$date);
            }
            $avail = (int) $row->units_total - (int) $row->units_sold - (int) $row->units_held;
            if ($avail < $units) {
                throw new \RuntimeException('Not enough rooms for '.$date);
            }
            $row->increment('units_held', $units);
        }
    }

    public function releaseHold(HotelRoomType $roomType, Carbon $checkIn, Carbon $checkOut, int $units = 1): void
    {
        foreach (self::nightDates($checkIn, $checkOut) as $date) {
            $row = HotelInventory::query()
                ->where('hotel_room_type_id', $roomType->id)
                ->whereDate('night_date', $date)
                ->lockForUpdate()
                ->first();
            if ($row && (int) $row->units_held >= $units) {
                $row->decrement('units_held', $units);
            }
        }
    }

    /**
     * After successful payment: move held to sold.
     */
    public function finalizeFromHold(HotelRoomType $roomType, Carbon $checkIn, Carbon $checkOut, int $units = 1): void
    {
        foreach (self::nightDates($checkIn, $checkOut) as $date) {
            $row = HotelInventory::query()
                ->where('hotel_room_type_id', $roomType->id)
                ->whereDate('night_date', $date)
                ->lockForUpdate()
                ->first();
            if (! $row) {
                continue;
            }
            if ((int) $row->units_held < $units) {
                throw new \RuntimeException('Hold mismatch for '.$date);
            }
            $row->decrement('units_held', $units);
            $row->increment('units_sold', $units);
        }
    }

    public function revertSold(HotelRoomType $roomType, Carbon $checkIn, Carbon $checkOut, int $units = 1): void
    {
        foreach (self::nightDates($checkIn, $checkOut) as $date) {
            $row = HotelInventory::query()
                ->where('hotel_room_type_id', $roomType->id)
                ->whereDate('night_date', $date)
                ->lockForUpdate()
                ->first();
            if ($row && (int) $row->units_sold >= $units) {
                $row->decrement('units_sold', $units);
            }
        }
    }
}
