<?php

namespace Tests\Feature;

use Tests\TestCase;

class ApiMyTest extends TestCase
{
    private string $base = '/api/v1/my';

    public function test_profile_returns_401_without_token(): void
    {
        $response = $this->getJson($this->base . '/profile');
        $response->assertStatus(401);
    }

    public function test_bookings_returns_401_without_token(): void
    {
        $response = $this->getJson($this->base . '/bookings');
        $response->assertStatus(401);
    }

    public function test_booking_by_id_returns_401_without_token(): void
    {
        $response = $this->getJson($this->base . '/booking/1');
        $response->assertStatus(401);
    }

    public function test_booking_android_returns_401_without_token(): void
    {
        $response = $this->getJson($this->base . '/booking/android/1');
        $response->assertStatus(401);
    }

    public function test_cancellations_returns_401_without_token(): void
    {
        $response = $this->getJson($this->base . '/cancellations');
        $response->assertStatus(401);
    }

    public function test_activities_returns_401_without_token(): void
    {
        $response = $this->getJson($this->base . '/activities');
        $response->assertStatus(401);
    }

    public function test_journey_returns_401_without_token(): void
    {
        $response = $this->getJson($this->base . '/journey');
        $response->assertStatus(401);
    }

    public function test_journey_by_id_returns_401_without_token(): void
    {
        $response = $this->getJson($this->base . '/journey/1');
        $response->assertStatus(401);
    }

    public function test_notifications_returns_401_without_token(): void
    {
        $response = $this->getJson($this->base . '/notifications');
        $response->assertStatus(401);
    }

    public function test_notifications_read_returns_401_without_token(): void
    {
        $response = $this->postJson($this->base . '/notifications/read', []);
        $response->assertStatus(401);
    }

    public function test_notifications_read_all_returns_401_without_token(): void
    {
        $response = $this->postJson($this->base . '/notifications/read/all', []);
        $response->assertStatus(401);
    }

    public function test_favourite_launches_returns_401_without_token(): void
    {
        $response = $this->getJson($this->base . '/favourite/launches');
        $response->assertStatus(401);
    }

    public function test_favourite_vehicles_returns_401_without_token(): void
    {
        $response = $this->getJson($this->base . '/favourite/vehicles');
        $response->assertStatus(401);
    }

    public function test_profile_update_returns_401_without_token(): void
    {
        $response = $this->postJson($this->base . '/profile/update', ['name' => 'New']);
        $response->assertStatus(401);
    }

    public function test_update_profile_returns_401_without_token(): void
    {
        $response = $this->postJson($this->base . '/update-profile', ['name' => 'New']);
        $response->assertStatus(401);
    }

    public function test_device_id_returns_401_without_token(): void
    {
        $response = $this->postJson($this->base . '/device-id', []);
        $response->assertStatus(401);
    }

    public function test_email_change_returns_401_without_token(): void
    {
        $response = $this->postJson($this->base . '/email/change', []);
        $response->assertStatus(401);
    }

    public function test_mobile_change_returns_401_without_token(): void
    {
        $response = $this->postJson($this->base . '/mobile/change', []);
        $response->assertStatus(401);
    }

    public function test_password_change_returns_401_without_token(): void
    {
        $response = $this->postJson($this->base . '/password/change', []);
        $response->assertStatus(401);
    }

    public function test_wallet_returns_401_without_token(): void
    {
        $response = $this->getJson($this->base . '/wallet');
        $response->assertStatus(401);
    }

    public function test_withdrawals_returns_401_without_token(): void
    {
        $response = $this->getJson($this->base . '/withdrawals');
        $response->assertStatus(401);
    }

    public function test_withdrawal_init_returns_401_without_token(): void
    {
        $response = $this->getJson($this->base . '/withdrawal-init');
        $response->assertStatus(401);
    }

    public function test_withdrawal_method_list_returns_401_without_token(): void
    {
        $response = $this->getJson($this->base . '/withdrawal-method-list');
        $response->assertStatus(401);
    }

    public function test_get_bookings_returns_401_without_token(): void
    {
        $response = $this->getJson($this->base . '/get-bookings');
        $response->assertStatus(401);
    }

    public function test_commission_history_returns_401_without_token(): void
    {
        $response = $this->getJson($this->base . '/commission/history');
        $response->assertStatus(401);
    }

    public function test_get_nid_number_returns_401_without_token(): void
    {
        $response = $this->postJson($this->base . '/get-nid-number', []);
        $response->assertStatus(401);
    }

    public function test_nid_verification_returns_401_without_token(): void
    {
        $response = $this->postJson($this->base . '/nid-verification', []);
        $response->assertStatus(401);
    }
}
