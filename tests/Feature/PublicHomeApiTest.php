<?php

namespace Tests\Feature;

use Tests\TestCase;

class PublicHomeApiTest extends TestCase
{
    public function test_popular_upcoming_trips_returns_json(): void
    {
        try {
            \Illuminate\Support\Facades\DB::connection()->getPdo();
        } catch (\Throwable) {
            $this->markTestSkipped('Database not available for API test.');
        }

        $this->getJson('/api/v1/public/popular-upcoming-trips?limit=3')
            ->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonStructure(['success', 'data']);
    }

    public function test_hotel_home_top_returns_json(): void
    {
        try {
            \Illuminate\Support\Facades\DB::connection()->getPdo();
        } catch (\Throwable) {
            $this->markTestSkipped('Database not available for API test.');
        }

        $this->getJson('/api/v1/hotel/home/top?limit=3')
            ->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonStructure(['success', 'data']);
    }

    public function test_offers_returns_json(): void
    {
        try {
            \Illuminate\Support\Facades\DB::connection()->getPdo();
        } catch (\Throwable) {
            $this->markTestSkipped('Database not available for API test.');
        }

        $this->getJson('/api/v1/offers')
            ->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonStructure(['success', 'data']);
    }
}
