<?php

namespace Tests\Feature;

use Tests\TestCase;

class ApiQuickbookTest extends TestCase
{
    private string $base = '/api/v1/quickbook';

    public function test_booking_returns_401_without_token(): void
    {
        $response = $this->getJson($this->base . '/booking/1');
        $response->assertStatus(401);
    }

    public function test_routes_returns_401_without_token(): void
    {
        $response = $this->getJson($this->base . '/routes');
        $response->assertStatus(401);
    }

    public function test_search_returns_401_without_token(): void
    {
        $response = $this->getJson($this->base . '/search');
        $response->assertStatus(401);
    }

    public function test_trip_returns_401_without_token(): void
    {
        $response = $this->getJson($this->base . '/trip/1');
        $response->assertStatus(401);
    }

    public function test_confirm_returns_401_without_token(): void
    {
        $response = $this->postJson($this->base . '/confirm', []);
        $response->assertStatus(401);
    }

    public function test_payment_returns_401_without_token(): void
    {
        $response = $this->postJson($this->base . '/payment', []);
        $response->assertStatus(401);
    }

    public function test_cart_add_returns_401_without_token(): void
    {
        $response = $this->postJson($this->base . '/cart/add', []);
        $response->assertStatus(401);
    }

    public function test_cart_add_deck_returns_401_without_token(): void
    {
        $response = $this->postJson($this->base . '/cart/add/deck', []);
        $response->assertStatus(401);
    }

    public function test_find_returns_401_without_token(): void
    {
        $response = $this->getJson($this->base . '/find');
        $response->assertStatus(401);
    }

    public function test_qr_returns_401_without_token(): void
    {
        $response = $this->getJson($this->base . '/qr');
        $response->assertStatus(401);
    }

    public function test_printed_returns_401_without_token(): void
    {
        $response = $this->postJson($this->base . '/printed', []);
        $response->assertStatus(401);
    }

    public function test_reprint_returns_401_without_token(): void
    {
        $response = $this->postJson($this->base . '/reprint', []);
        $response->assertStatus(401);
    }

    public function test_print_all_returns_401_without_token(): void
    {
        $response = $this->postJson($this->base . '/print/all', []);
        $response->assertStatus(401);
    }

    public function test_reprint_confirm_returns_401_without_token(): void
    {
        $response = $this->postJson($this->base . '/reprint/confirm', []);
        $response->assertStatus(401);
    }

    public function test_details_returns_401_without_token(): void
    {
        $response = $this->getJson($this->base . '/details/1');
        $response->assertStatus(401);
    }

    public function test_deck_returns_401_without_token(): void
    {
        $response = $this->postJson($this->base . '/deck', []);
        $response->assertStatus(401);
    }
}
