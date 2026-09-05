<?php

namespace App\Services\Hotel;

use App\Models\HotelInventory;
use App\Models\HotelRoomType;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

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
     * Remaining bookable units for the stay (min across nights).
     * Formula: units_total - units_sold - units_held.
     */
    public function availableUnits(
        HotelRoomType $roomType,
        Carbon $checkIn,
        Carbon $checkOut,
    ): int {
        if ($this->isStopSold($roomType, $checkIn, $checkOut)) {
            return 0;
        }

        $dates = self::nightDates($checkIn, $checkOut);
        if ($dates === []) {
            return 0;
        }

        $min = null;
        foreach ($dates as $date) {
            $this->ensureInventoryRow($roomType, $date);
            $row = HotelInventory::query()
                ->where('hotel_room_type_id', $roomType->id)
                ->whereDate('night_date', $date)
                ->first();
            if (! $row) {
                return 0;
            }
            $avail = max(
                0,
                (int) $row->units_total - (int) $row->units_sold - (int) $row->units_held,
            );
            $min = $min === null ? $avail : min($min, $avail);
            if ($min === 0) {
                return 0;
            }
        }

        return (int) ($min ?? 0);
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
        $this->assertNotStopSold($roomType, $checkIn, $checkOut);

        $dates = self::nightDates($checkIn, $checkOut);
        if ($dates === []) {
            throw new \InvalidArgumentException('Invalid stay range');
        }

        foreach ($dates as $date) {
            $this->ensureInventoryRow($roomType, $date);
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
        $this->assertNotStopSold($roomType, $checkIn, $checkOut);

        foreach (self::nightDates($checkIn, $checkOut) as $date) {
            $this->ensureInventoryRow($roomType, $date);
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

    /**
     * Direct sell (no prior hold) — merchant create without skip_inventory_reserve.
     */
    public function sell(HotelRoomType $roomType, Carbon $checkIn, Carbon $checkOut, int $units = 1): void
    {
        $this->assertNotStopSold($roomType, $checkIn, $checkOut);

        foreach (self::nightDates($checkIn, $checkOut) as $date) {
            $this->ensureInventoryRow($roomType, $date);
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

    /**
     * Seed a night row from module capacity when missing.
     */
    public function ensureInventoryRow(HotelRoomType $roomType, string $date): void
    {
        if (! Schema::hasTable('hotel_inventory')) {
            return;
        }
        if (HotelInventory::query()
            ->where('hotel_room_type_id', $roomType->id)
            ->whereDate('night_date', $date)
            ->exists()) {
            return;
        }

        $total = $this->defaultUnitsTotalForRoomType($roomType);
        try {
            HotelInventory::query()->create([
                'hotel_room_type_id' => $roomType->id,
                'night_date' => $date,
                'units_total' => $total,
                'units_sold' => 0,
                'units_held' => 0,
            ]);
        } catch (QueryException $e) {
            $code = (int) ($e->errorInfo[1] ?? 0);
            if ($code === 1062 || $e->getCode() === '23000' || $code === 19) {
                return;
            }
            throw $e;
        }
    }

    /**
     * When {@see config('hotel.rooms_treat_missing_inventory_as_available')} is true, create a
     * `hotel_inventory` row for the night so holds/quotes can proceed without a manual seed.
     */
    private function ensureInventoryRowForDateIfRelaxed(HotelRoomType $roomType, string $date): void
    {
        if (! (bool) config('hotel.rooms_treat_missing_inventory_as_available', false)) {
            return;
        }
        $this->ensureInventoryRow($roomType, $date);
    }

    private function assertNotStopSold(HotelRoomType $roomType, Carbon $checkIn, Carbon $checkOut): void
    {
        [$hotelId, $moduleRoomTypeId] = $this->stopSaleScope($roomType);
        if ($hotelId < 1) {
            return;
        }
        app(HotelStopSaleService::class)->assertBookable($hotelId, $moduleRoomTypeId, $checkIn, $checkOut);
    }

    private function isStopSold(HotelRoomType $roomType, Carbon $checkIn, Carbon $checkOut): bool
    {
        [$hotelId, $moduleRoomTypeId] = $this->stopSaleScope($roomType);
        if ($hotelId < 1) {
            return false;
        }

        return app(HotelStopSaleService::class)->blocksStay($hotelId, $moduleRoomTypeId, $checkIn, $checkOut);
    }

    /**
     * @return array{0:int,1:?int}
     */
    private function stopSaleScope(HotelRoomType $roomType): array
    {
        $hotelId = (int) $roomType->hotel_id;
        $moduleRoomTypeId = null;
        if (preg_match('/^mod_hr_(\d+)$/', (string) $roomType->code, $m) && Schema::hasTable('hotel_rooms')) {
            $row = DB::table('hotel_rooms')->where('id', (int) $m[1])->first();
            if ($row !== null) {
                if ($hotelId < 1) {
                    $hotelId = (int) ($row->hotel_id ?? 0);
                }
                if (isset($row->room_type_id) && (int) $row->room_type_id > 0) {
                    $moduleRoomTypeId = (int) $row->room_type_id;
                }
            }
        }

        return [$hotelId, $moduleRoomTypeId];
    }

    private function defaultUnitsTotalForRoomType(HotelRoomType $roomType): int
    {
        if (preg_match('/^mod_hr_(\d+)$/', (string) $roomType->code, $m) && Schema::hasTable('hotel_rooms')) {
            $row = DB::table('hotel_rooms')->where('id', (int) $m[1])->first();
            if ($row !== null) {
                $tr = $row->total_rooms ?? null;
                if ($tr !== null && (int) $tr > 0) {
                    return (int) $tr;
                }
            }
        }

        return max(1, (int) config('hotel.default_inventory_units_per_night', 10));
    }
}
