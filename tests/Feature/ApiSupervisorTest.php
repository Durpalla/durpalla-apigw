<?php

namespace Tests\Feature;

use Tests\TestCase;

class ApiSupervisorTest extends TestCase
{
    private string $base = '/api/v1/supervisor';

    public function test_jobs_returns_401_without_token(): void
    {
        $response = $this->getJson($this->base . '/jobs');
        $response->assertStatus(401);
    }

    public function test_booking_history_returns_401_without_token(): void
    {
        $response = $this->getJson($this->base . '/booking/history');
        $response->assertStatus(401);
    }

    public function test_cart_returns_401_without_token(): void
    {
        $response = $this->getJson($this->base . '/cart');
        $response->assertStatus(401);
    }

    public function test_cart_send_returns_401_without_token(): void
    {
        $response = $this->postJson($this->base . '/cart/send', []);
        $response->assertStatus(401);
    }

    public function test_booking_group_wize_print_returns_401_without_token(): void
    {
        $response = $this->getJson($this->base . '/booking/group-wize-print');
        $response->assertStatus(401);
    }

    public function test_scan_history_returns_401_without_token(): void
    {
        $response = $this->getJson($this->base . '/scan/history');
        $response->assertStatus(401);
    }

    public function test_vehicles_returns_401_without_token(): void
    {
        $response = $this->getJson($this->base . '/vehicles');
        $response->assertStatus(401);
    }

    public function test_schedules_returns_401_without_token(): void
    {
        $response = $this->getJson($this->base . '/schedules');
        $response->assertStatus(401);
    }

    public function test_available_vehicle_returns_401_without_token(): void
    {
        $response = $this->getJson($this->base . '/available/vehicle');
        $response->assertStatus(401);
    }

    public function test_wallet_returns_401_without_token(): void
    {
        $response = $this->getJson($this->base . '/wallet');
        $response->assertStatus(401);
    }

    public function test_summary_returns_401_without_token(): void
    {
        $response = $this->getJson($this->base . '/summary');
        $response->assertStatus(401);
    }

    public function test_summary_send_returns_401_without_token(): void
    {
        $response = $this->postJson($this->base . '/summary/send', []);
        $response->assertStatus(401);
    }

    public function test_booking_cancel_returns_401_without_token(): void
    {
        $response = $this->postJson($this->base . '/booking/cancel', []);
        $response->assertStatus(401);
    }

    public function test_cancellations_returns_401_without_token(): void
    {
        $response = $this->getJson($this->base . '/cancellations');
        $response->assertStatus(401);
    }

    public function test_cancellations_show_returns_401_without_token(): void
    {
        $response = $this->getJson($this->base . '/cancellations/1/show');
        $response->assertStatus(401);
    }

    public function test_cancellations_update_returns_401_without_token(): void
    {
        $response = $this->putJson($this->base . '/cancellations/1/update', []);
        $response->assertStatus(401);
    }
}
