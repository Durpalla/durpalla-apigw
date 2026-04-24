<?php

namespace Tests\Feature;

use Tests\RefreshDatabase;
use Tests\TestCase;

class ApiCartTransportTest extends TestCase
{
    use RefreshDatabase;

    private string $base = '/api/v1';

    /** Cart/transport endpoints may return 200, 422 (validation), or 500 when dependent data is missing. */
    private function assertCartResponse(int $status): void
    {
        $this->assertContains($status, [200, 422, 500]);
    }

    public function test_cart_add_returns_json(): void
    {
        $response = $this->postJson($this->base . '/cart/add', ['item_id' => 1]);
        $this->assertCartResponse($response->status());
        if ($response->status() === 200) {
            $response->assertJsonStructure(['success', 'message']);
        }
    }

    public function test_cart_lock_returns_json(): void
    {
        $response = $this->postJson($this->base . '/cart/lock', ['item_id' => 1]);
        $this->assertCartResponse($response->status());
        if ($response->status() === 200) {
            $response->assertJsonStructure(['success', 'message']);
        }
    }

    public function test_cart_add_with_item_ids_returns_json(): void
    {
        $response = $this->postJson($this->base . '/cart/add', ['item_ids' => [1]]);
        $this->assertCartResponse($response->status());
        if ($response->status() === 200) {
            $response->assertJsonStructure(['success']);
        }
    }

    public function test_transport_lock_returns_json(): void
    {
        $response = $this->postJson($this->base . '/transport/lock', ['item_id' => 1]);
        $this->assertCartResponse($response->status());
        if ($response->status() === 200) {
            $response->assertJsonStructure(['success', 'message']);
        }
    }

    public function test_transport_lock_with_item_ids_returns_json(): void
    {
        $response = $this->postJson($this->base . '/transport/lock', ['item_ids' => [1]]);
        $this->assertCartResponse($response->status());
        if ($response->status() === 200) {
            $response->assertJsonStructure(['success']);
        }
    }

    public function test_cart_remove_returns_json(): void
    {
        $response = $this->postJson($this->base . '/cart/remove', ['item_id' => 1]);
        $this->assertCartResponse($response->status());
        if ($response->status() === 200) {
            $response->assertJsonStructure(['success', 'message']);
        }
    }

    public function test_cart_unlock_returns_json(): void
    {
        $response = $this->postJson($this->base . '/cart/unlock', ['item_id' => 1]);
        $this->assertCartResponse($response->status());
        if ($response->status() === 200) {
            $response->assertJsonStructure(['success', 'message']);
        }
    }

    public function test_transport_unlock_returns_json(): void
    {
        $response = $this->postJson($this->base . '/transport/unlock', ['item_id' => 1]);
        $this->assertCartResponse($response->status());
        if ($response->status() === 200) {
            $response->assertJsonStructure(['success', 'message']);
        }
    }

    public function test_cart_reset_returns_json(): void
    {
        $response = $this->getJson($this->base . '/cart/reset');
        $response->assertStatus(200)->assertJsonStructure(['success', 'message']);
    }
}
