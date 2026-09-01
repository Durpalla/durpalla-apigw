<?php

namespace Tests\Unit;

use App\Models\Hotel;
use App\Models\HotelInventory;
use App\Models\HotelRoomType;
use App\Services\Hotel\HotelInventoryService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\RefreshDatabase;
use Tests\TestCase;

class HotelInventoryServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_night_dates_excludes_checkout_day(): void
    {
        $dates = HotelInventoryService::nightDates(
            Carbon::parse('2026-08-10'),
            Carbon::parse('2026-08-13'),
        );

        $this->assertSame(['2026-08-10', '2026-08-11', '2026-08-12'], $dates);
    }

    public function test_apply_hold_increments_units_held(): void
    {
        $room = $this->seedRoom(units: 4);
        $svc = app(HotelInventoryService::class);

        $svc->applyHold(
            $room,
            Carbon::parse('2026-11-01'),
            Carbon::parse('2026-11-03'),
            2,
        );

        $this->assertSame(2, (int) HotelInventory::query()
            ->where('hotel_room_type_id', $room->id)
            ->whereDate('night_date', '2026-11-01')
            ->value('units_held'));
        $this->assertSame(2, (int) HotelInventory::query()
            ->where('hotel_room_type_id', $room->id)
            ->whereDate('night_date', '2026-11-02')
            ->value('units_held'));
    }

    public function test_apply_hold_throws_when_not_enough_rooms(): void
    {
        $room = $this->seedRoom(units: 1);
        $svc = app(HotelInventoryService::class);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Not enough rooms');

        $svc->applyHold(
            $room,
            Carbon::parse('2026-11-01'),
            Carbon::parse('2026-11-02'),
            2,
        );
    }

    public function test_release_hold_decrements_units_held(): void
    {
        $room = $this->seedRoom(units: 3);
        $svc = app(HotelInventoryService::class);
        $in = Carbon::parse('2026-11-01');
        $out = Carbon::parse('2026-11-02');

        $svc->applyHold($room, $in, $out, 1);
        $svc->releaseHold($room, $in, $out, 1);

        $this->assertSame(0, (int) HotelInventory::query()
            ->where('hotel_room_type_id', $room->id)
            ->whereDate('night_date', '2026-11-01')
            ->value('units_held'));
    }

    public function test_available_units_reflects_holds_and_rejects_overbook(): void
    {
        $room = $this->seedRoom(units: 10);
        $svc = app(HotelInventoryService::class);
        $in = Carbon::parse('2026-11-01');
        $out = Carbon::parse('2026-11-02');

        $this->assertSame(10, $svc->availableUnits($room, $in, $out));

        $svc->applyHold($room, $in, $out, 10);
        $this->assertSame(0, $svc->availableUnits($room, $in, $out));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Not enough rooms');
        $svc->applyHold($room, $in, $out, 1);
    }

    private function seedRoom(int $units = 5): HotelRoomType
    {
        $hotelAttrs = [
            'name' => 'Inv Hotel '.uniqid(),
            'status' => 1,
        ];
        if (Schema::hasColumn('hotels', 'city')) {
            $hotelAttrs['city'] = 'Sylhet';
        }
        if (Schema::hasColumn('hotels', 'slug')) {
            $hotelAttrs['slug'] = 'inv-hotel-'.uniqid();
        }
        if (Schema::hasColumn('hotels', 'is_approved')) {
            $hotelAttrs['is_approved'] = true;
        }

        $hotel = Hotel::query()->create($hotelAttrs);

        $roomAttrs = [
            'hotel_id' => $hotel->id,
            'code' => 'INV-'.uniqid(),
            'title' => 'Inventory Room',
            'max_occupancy' => 2,
            'base_price_per_night' => 900,
            'currency' => 'BDT',
            'status' => 1,
        ];
        if (Schema::hasColumn('hotel_room_types', 'category')) {
            $roomAttrs['category'] = HotelRoomType::CATEGORY_ROOM;
        }

        $room = HotelRoomType::query()->create($roomAttrs);

        foreach (['2026-11-01', '2026-11-02'] as $night) {
            HotelInventory::query()->create([
                'hotel_room_type_id' => $room->id,
                'night_date' => $night,
                'units_total' => $units,
                'units_sold' => 0,
                'units_held' => 0,
            ]);
        }

        return $room;
    }
}
