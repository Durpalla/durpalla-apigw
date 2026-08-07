<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Hotel;
use App\Models\HotelHold;
use App\Models\HotelInventory;
use App\Models\HotelReservation;
use App\Models\HotelRoomType;
use Illuminate\Support\Facades\Schema;
use Tests\RefreshDatabase;
use Tests\TestCase;

/**
 * Customer hotel booking flow: quote → hold → confirm / release.
 */
class HotelBookingApiTest extends TestCase
{
    use RefreshDatabase;

    private string $checkIn = '2026-09-10';

    private string $checkOut = '2026-09-12';

    public function test_quote_returns_stay_totals(): void
    {
        $room = $this->seedHotelRoom(price: 1500);

        $this->postJson('/api/v1/hotel/quote', [
            'room_type_id' => $room->id,
            'check_in' => $this->checkIn,
            'check_out' => $this->checkOut,
            'adults' => 2,
            'children' => 0,
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.nights', 2)
            ->assertJsonPath('data.room_subtotal', 3000)
            ->assertJsonStructure([
                'success',
                'data' => ['nights', 'room_subtotal', 'total', 'currency', 'lines'],
            ]);
    }

    public function test_quote_rejects_invalid_dates(): void
    {
        $room = $this->seedHotelRoom();

        $this->postJson('/api/v1/hotel/quote', [
            'room_type_id' => $room->id,
            'check_in' => $this->checkOut,
            'check_out' => $this->checkIn,
        ])
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_hold_requires_customer_auth(): void
    {
        $room = $this->seedHotelRoom();

        $this->postJson('/api/v1/hotel/hold', [
            'room_type_id' => $room->id,
            'check_in' => $this->checkIn,
            'check_out' => $this->checkOut,
        ], ['Idempotency-Key' => 'hold-no-auth'])
            ->assertStatus(401);
    }

    public function test_hold_requires_idempotency_key(): void
    {
        $customer = Customer::factory()->create();
        $room = $this->seedHotelRoom();

        $this->actingAs($customer, 'customer')
            ->postJson('/api/v1/hotel/hold', [
                'room_type_id' => $room->id,
                'check_in' => $this->checkIn,
                'check_out' => $this->checkOut,
            ])
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_customer_can_create_hold_and_inventory_is_reserved(): void
    {
        $customer = Customer::factory()->create();
        $room = $this->seedHotelRoom(inventoryUnits: 3);

        $response = $this->actingAs($customer, 'customer')
            ->postJson('/api/v1/hotel/hold', [
                'room_type_id' => $room->id,
                'check_in' => $this->checkIn,
                'check_out' => $this->checkOut,
                'adults' => 2,
                'children' => 0,
            ], ['Idempotency-Key' => 'hold-ok-1']);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'data' => ['hold_id', 'expires_at', 'total', 'status', 'quote'],
            ]);

        $holdId = (int) $response->json('data.hold_id');
        $this->assertDatabaseHas('hotel_holds', [
            'id' => $holdId,
            'user_id' => $customer->id,
            'hotel_room_type_id' => $room->id,
            'status' => HotelHold::STATUS_PENDING,
        ]);

        $held = HotelInventory::query()
            ->where('hotel_room_type_id', $room->id)
            ->whereDate('night_date', $this->checkIn)
            ->value('units_held');
        $this->assertSame(1, (int) $held);
    }

    public function test_hold_is_idempotent_for_same_customer_and_key(): void
    {
        $customer = Customer::factory()->create();
        $room = $this->seedHotelRoom();

        $first = $this->actingAs($customer, 'customer')
            ->postJson('/api/v1/hotel/hold', [
                'room_type_id' => $room->id,
                'check_in' => $this->checkIn,
                'check_out' => $this->checkOut,
            ], ['Idempotency-Key' => 'idem-same']);

        $second = $this->actingAs($customer, 'customer')
            ->postJson('/api/v1/hotel/hold', [
                'room_type_id' => $room->id,
                'check_in' => $this->checkIn,
                'check_out' => $this->checkOut,
            ], ['Idempotency-Key' => 'idem-same']);

        $first->assertOk();
        $second->assertOk();
        $this->assertSame(
            (int) $first->json('data.hold_id'),
            (int) $second->json('data.hold_id'),
        );
        $this->assertSame(1, HotelHold::query()->where('idempotency_key', 'idem-same')->count());
    }

    public function test_hold_fails_when_inventory_exhausted(): void
    {
        $customer = Customer::factory()->create();
        $room = $this->seedHotelRoom(inventoryUnits: 1);

        $this->actingAs($customer, 'customer')
            ->postJson('/api/v1/hotel/hold', [
                'room_type_id' => $room->id,
                'check_in' => $this->checkIn,
                'check_out' => $this->checkOut,
            ], ['Idempotency-Key' => 'hold-last-unit'])
            ->assertOk();

        $other = Customer::factory()->create();
        $this->actingAs($other, 'customer')
            ->postJson('/api/v1/hotel/hold', [
                'room_type_id' => $room->id,
                'check_in' => $this->checkIn,
                'check_out' => $this->checkOut,
            ], ['Idempotency-Key' => 'hold-no-stock'])
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_customer_can_release_hold_via_post(): void
    {
        $customer = Customer::factory()->create();
        $room = $this->seedHotelRoom(inventoryUnits: 2);

        $holdId = (int) $this->actingAs($customer, 'customer')
            ->postJson('/api/v1/hotel/hold', [
                'room_type_id' => $room->id,
                'check_in' => $this->checkIn,
                'check_out' => $this->checkOut,
            ], ['Idempotency-Key' => 'hold-to-release'])
            ->assertOk()
            ->json('data.hold_id');

        $this->actingAs($customer, 'customer')
            ->postJson('/api/v1/hotel/hold/release', ['hold_id' => $holdId])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('hotel_holds', [
            'id' => $holdId,
            'status' => HotelHold::STATUS_CANCELLED,
        ]);

        $held = HotelInventory::query()
            ->where('hotel_room_type_id', $room->id)
            ->whereDate('night_date', $this->checkIn)
            ->value('units_held');
        $this->assertSame(0, (int) $held);
    }

    public function test_customer_can_confirm_hold_into_booking(): void
    {
        $customer = Customer::factory()->create();
        $room = $this->seedHotelRoom();

        $holdId = (int) $this->actingAs($customer, 'customer')
            ->postJson('/api/v1/hotel/hold', [
                'room_type_id' => $room->id,
                'check_in' => $this->checkIn,
                'check_out' => $this->checkOut,
                'adults' => 2,
            ], ['Idempotency-Key' => 'hold-to-confirm'])
            ->assertOk()
            ->json('data.hold_id');

        $confirm = $this->actingAs($customer, 'customer')
            ->postJson('/api/v1/hotel/booking/confirm', ['hold_id' => $holdId]);

        $confirm->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'booking_id',
                'order_id',
                'booking' => ['id', 'status', 'total_payable'],
                'payment' => ['id', 'transaction_id', 'paid_amount'],
                'hotel' => ['name', 'check_in', 'check_out'],
            ]);

        $bookingId = (int) $confirm->json('booking_id');
        $this->assertDatabaseHas('bookings', [
            'id' => $bookingId,
            'customer_id' => $customer->id,
        ]);
        $this->assertDatabaseHas('hotel_holds', [
            'id' => $holdId,
            'status' => HotelHold::STATUS_CONSUMED,
        ]);
        $this->assertSame(
            1,
            HotelReservation::query()->where('hotel_hold_id', $holdId)->count(),
        );
    }

    public function test_confirm_rejects_foreign_hold(): void
    {
        $owner = Customer::factory()->create();
        $intruder = Customer::factory()->create();
        $room = $this->seedHotelRoom();

        $holdId = (int) $this->actingAs($owner, 'customer')
            ->postJson('/api/v1/hotel/hold', [
                'room_type_id' => $room->id,
                'check_in' => $this->checkIn,
                'check_out' => $this->checkOut,
            ], ['Idempotency-Key' => 'owner-hold'])
            ->assertOk()
            ->json('data.hold_id');

        $this->actingAs($intruder, 'customer')
            ->postJson('/api/v1/hotel/booking/confirm', ['hold_id' => $holdId])
            ->assertStatus(404);
    }

    /**
     * @return HotelRoomType
     */
    private function seedHotelRoom(float $price = 1000, int $inventoryUnits = 5): HotelRoomType
    {
        $hotelAttrs = [
            'name' => 'Test Hotel '.uniqid(),
            'status' => 1,
        ];
        if (Schema::hasColumn('hotels', 'city')) {
            $hotelAttrs['city'] = 'Dhaka';
        }
        if (Schema::hasColumn('hotels', 'slug')) {
            $hotelAttrs['slug'] = 'test-hotel-'.uniqid();
        }
        if (Schema::hasColumn('hotels', 'is_approved')) {
            $hotelAttrs['is_approved'] = true;
        }
        if (Schema::hasColumn('hotels', 'star_rating')) {
            $hotelAttrs['star_rating'] = 4;
        }
        if (Schema::hasColumn('hotels', 'aggregate_rating')) {
            $hotelAttrs['aggregate_rating'] = 4.5;
        }
        if (Schema::hasColumn('hotels', 'review_count')) {
            $hotelAttrs['review_count'] = 0;
        }

        $hotel = Hotel::query()->create($hotelAttrs);

        $roomAttrs = [
            'hotel_id' => $hotel->id,
            'code' => 'STD-'.uniqid(),
            'title' => 'Standard',
            'max_occupancy' => 2,
            'base_price_per_night' => $price,
            'currency' => 'BDT',
            'status' => 1,
        ];
        if (Schema::hasColumn('hotel_room_types', 'category')) {
            $roomAttrs['category'] = HotelRoomType::CATEGORY_ROOM;
        }

        $room = HotelRoomType::query()->create($roomAttrs);

        foreach ([$this->checkIn, '2026-09-11'] as $night) {
            HotelInventory::query()->create([
                'hotel_room_type_id' => $room->id,
                'night_date' => $night,
                'units_total' => $inventoryUnits,
                'units_sold' => 0,
                'units_held' => 0,
            ]);
        }

        return $room->fresh();
    }
}
