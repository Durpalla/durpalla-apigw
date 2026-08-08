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

    public function test_public_app_config_returns_json(): void
    {
        $this->getJson('/api/v1/public/app-config?platform=android')
            ->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'success',
                'data' => [
                    'version' => ['min', 'latest', 'force_update', 'store_url'],
                    'sections' => [
                        'my_offers',
                        'my_trips',
                        'upcoming_trips',
                        'gallery_slider',
                    ],
                ],
            ]);
    }
}
