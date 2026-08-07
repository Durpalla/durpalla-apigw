<?php

namespace Tests\Unit;

use App\Models\Customer;
use App\Models\Hotel;
use App\Models\HotelHold;
use App\Models\HotelInventory;
use App\Models\HotelRoomType;
use App\Models\User;
use App\Services\Hotel\HotelBookingService;
use Illuminate\Support\Facades\Schema;
use Tests\RefreshDatabase;
use Tests\TestCase;
use TypeError;

/**
 * Service-level hotel hold/confirm coverage (incl. Customer vs User regression).
 */
class HotelBookingServiceTest extends TestCase
{
    use RefreshDatabase;

    private string $checkIn = '2026-10-01';

    private string $checkOut = '2026-10-03';

    public function test_create_hold_accepts_customer_not_staff_user(): void
    {
        $customer = Customer::factory()->create();
        $room = $this->seedRoom();
        $svc = app(HotelBookingService::class);

        $hold = $svc->createHold($customer, [
            'check_in' => $this->checkIn,
            'check_out' => $this->checkOut,
            'adults' => 2,
            'children' => 0,
            'lines' => [['room_type_id' => $room->id, 'quantity' => 1]],
        ], 'svc-hold-1');

        $this->assertInstanceOf(HotelHold::class, $hold);
        $this->assertSame($customer->id, (int) $hold->user_id);
        $this->assertSame(HotelHold::STATUS_PENDING, $hold->status);
        $this->assertGreaterThan(0, (float) $hold->total_amount);
    }

    public function test_create_hold_rejects_staff_user_type(): void
    {
        $room = $this->seedRoom();
        $svc = app(HotelBookingService::class);
        $staff = new User(['name' => 'Staff', 'email' => 'staff@example.com']);
        $staff->id = 999999;

        $this->expectException(TypeError::class);
        $svc->createHold($staff, [
            'check_in' => $this->checkIn,
            'check_out' => $this->checkOut,
            'lines' => [['room_type_id' => $room->id, 'quantity' => 1]],
        ], 'svc-hold-staff');
    }

    public function test_create_hold_idempotent_and_release_frees_inventory(): void
    {
        $customer = Customer::factory()->create();
        $room = $this->seedRoom(units: 2);
        $svc = app(HotelBookingService::class);

        $a = $svc->createHold($customer, [
            'check_in' => $this->checkIn,
            'check_out' => $this->checkOut,
            'lines' => [['room_type_id' => $room->id, 'quantity' => 1]],
        ], 'svc-idem');
        $b = $svc->createHold($customer, [
            'check_in' => $this->checkIn,
            'check_out' => $this->checkOut,
            'lines' => [['room_type_id' => $room->id, 'quantity' => 1]],
        ], 'svc-idem');

        $this->assertSame($a->id, $b->id);
        $this->assertSame(1, (int) HotelInventory::query()
            ->where('hotel_room_type_id', $room->id)
            ->whereDate('night_date', $this->checkIn)
            ->value('units_held'));

        $this->assertTrue($svc->releaseHold($customer, $a->id));
        $this->assertSame(0, (int) HotelInventory::query()
            ->where('hotel_room_type_id', $room->id)
            ->whereDate('night_date', $this->checkIn)
            ->value('units_held'));
        $this->assertSame(HotelHold::STATUS_CANCELLED, $a->fresh()->status);
    }

    public function test_confirm_from_hold_creates_booking_payment_and_reservation(): void
    {
        $customer = Customer::factory()->create();
        $room = $this->seedRoom();
        $svc = app(HotelBookingService::class);

        $hold = $svc->createHold($customer, [
            'check_in' => $this->checkIn,
            'check_out' => $this->checkOut,
            'adults' => 2,
            'lines' => [['room_type_id' => $room->id, 'quantity' => 1]],
        ], 'svc-confirm');

        $result = $svc->confirmFromHold($customer, $hold->id);

        $this->assertArrayHasKey('booking', $result);
        $this->assertArrayHasKey('payment', $result);
        $this->assertArrayHasKey('reservation', $result);
        $this->assertSame($customer->id, (int) $result['booking']->customer_id);
        $this->assertSame($hold->id, (int) $result['reservation']->hotel_hold_id);
        $this->assertSame(HotelHold::STATUS_CONSUMED, $hold->fresh()->status);
    }

    private function seedRoom(int $units = 5): HotelRoomType
    {
        $hotelAttrs = [
            'name' => 'Svc Hotel '.uniqid(),
            'status' => 1,
        ];
        if (Schema::hasColumn('hotels', 'city')) {
            $hotelAttrs['city'] = 'Chittagong';
        }
        if (Schema::hasColumn('hotels', 'slug')) {
            $hotelAttrs['slug'] = 'svc-hotel-'.uniqid();
        }
        if (Schema::hasColumn('hotels', 'is_approved')) {
            $hotelAttrs['is_approved'] = true;
        }

        $hotel = Hotel::query()->create($hotelAttrs);

        $roomAttrs = [
            'hotel_id' => $hotel->id,
            'code' => 'DLX-'.uniqid(),
            'title' => 'Deluxe',
            'max_occupancy' => 3,
            'base_price_per_night' => 2000,
            'currency' => 'BDT',
            'status' => 1,
        ];
        if (Schema::hasColumn('hotel_room_types', 'category')) {
            $roomAttrs['category'] = HotelRoomType::CATEGORY_ROOM;
        }

        $room = HotelRoomType::query()->create($roomAttrs);

        foreach ([$this->checkIn, '2026-10-02'] as $night) {
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
