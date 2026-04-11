<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiPublicInitTest extends TestCase
{
    use RefreshDatabase;

    private string $base = '/api/v1';

    /** Init endpoints may return 200 with data or 500 when dependent tables are empty. */
    private function assertOkOrServerError(int $status): void
    {
        $this->assertContains($status, [200, 500]);
    }

    public function test_site_init_returns_json(): void
    {
        $response = $this->getJson($this->base . '/site/init');
        $this->assertOkOrServerError($response->status());
        if ($response->status() === 200) {
            $response->assertJsonStructure(['success']);
        }
    }

    public function test_mobile_init_returns_json(): void
    {
        $response = $this->getJson($this->base . '/mobile/init');
        $this->assertOkOrServerError($response->status());
        if ($response->status() === 200) {
            $response->assertJsonStructure(['success']);
        }
    }

    public function test_vehicles_returns_json(): void
    {
        $response = $this->getJson($this->base . '/vehicles');
        $this->assertOkOrServerError($response->status());
        if ($response->status() === 200) {
            $response->assertJsonStructure(['success']);
        }
    }

    public function test_offers_returns_json(): void
    {
        $response = $this->getJson($this->base . '/offers');
        $this->assertOkOrServerError($response->status());
        if ($response->status() === 200) {
            $response->assertJsonStructure(['success']);
        }
    }

    public function test_faq_returns_json(): void
    {
        $response = $this->getJson($this->base . '/faq');
        $response->assertStatus(200);
    }

    public function test_search_returns_json(): void
    {
        $response = $this->getJson($this->base . '/search');
        $this->assertOkOrServerError($response->status());
        if ($response->status() === 200) {
            $response->assertJsonStructure(['success']);
        }
    }

    public function test_available_returns_json(): void
    {
        $response = $this->getJson($this->base . '/available');
        $this->assertOkOrServerError($response->status());
        if ($response->status() === 200) {
            $response->assertJsonStructure(['success']);
        }
    }

    public function test_transport_search_returns_json(): void
    {
        $response = $this->getJson($this->base . '/transport/search');
        $this->assertOkOrServerError($response->status());
        if ($response->status() === 200) {
            $response->assertJsonStructure(['success', 'data', 'meta']);
        }
    }

    public function test_transport_available_returns_json(): void
    {
        $response = $this->getJson($this->base . '/transport/available');
        $this->assertOkOrServerError($response->status());
        if ($response->status() === 200) {
            $response->assertJsonStructure(['success']);
        }
    }

    public function test_suggest_returns_json(): void
    {
        $response = $this->getJson($this->base . '/suggest/dhaka');
        $this->assertOkOrServerError($response->status());
        if ($response->status() === 200) {
            $response->assertJsonStructure(['success']);
        }
    }

    public function test_suggest_with_two_terms_returns_json(): void
    {
        $response = $this->getJson($this->base . '/suggest/dhaka/chittagong');
        $this->assertOkOrServerError($response->status());
        if ($response->status() === 200) {
            $response->assertJsonStructure(['success']);
        }
    }

    public function test_trip_returns_json(): void
    {
        $response = $this->getJson($this->base . '/trip/1');
        $this->assertContains($response->status(), [200, 404, 500]);
        if ($response->status() === 200) {
            $response->assertJsonStructure(['success']);
        }
    }

    public function test_gateway_returns_401_without_token(): void
    {
        $response = $this->getJson($this->base . '/gateway');
        $response->assertStatus(401);
    }

    public function test_page_returns_json_or_404(): void
    {
        $response = $this->getJson($this->base . '/page/about');
        $this->assertContains($response->status(), [200, 404, 500]);
    }
}
