<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Smoke tests. With a migrated test DB, add coverage for concurrent holds on the
 * last inventory unit via {@see HotelBookingService::createHold} in a feature test.
 */
class HotelApiTest extends TestCase
{
    public function test_hotel_search_returns_json_when_database_available(): void
    {
        try {
            \Illuminate\Support\Facades\DB::connection()->getPdo();
        } catch (\Throwable) {
            $this->markTestSkipped('Database not available for API test.');
        }

        $this->getJson('/api/v1/hotel/search?city=TestCity&check_in=2026-08-01&check_out=2026-08-03')
            ->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonStructure(['success', 'data']);
    }

    public function test_hotel_hold_requires_auth(): void
    {
        $this->postJson('/api/v1/hotel/hold', [
            'room_type_id' => 1,
            'check_in' => '2026-08-01',
            'check_out' => '2026-08-02',
        ], ['Idempotency-Key' => 'abc123'])
            ->assertStatus(401);
    }

    public function test_hotel_hold_release_post_requires_auth(): void
    {
        $this->postJson('/api/v1/hotel/hold/release', [
            'hold_id' => 1,
        ])->assertStatus(401);
    }
}
