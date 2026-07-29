<?php

namespace Tests\Feature\Api;

use App\Models\Agent;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Smoke tests for the agent mobile API moved from durpalla to durpalla-apigw
 * (see routes/api/v1/agent.php). Most assertions here are DB-independent
 * (validation / auth-guard failures short-circuit before any query), so they
 * run even without a migrated test database. The seeded login test is best
 * effort and skips itself when the `agents` table isn't available.
 */
class AgentApiTest extends TestCase
{
    private string $base = '/api/v1/agent';

    public function test_login_fails_validation_without_credentials(): void
    {
        $response = $this->postJson($this->base . '/auth/login', []);

        $response->assertStatus(200)
            ->assertJson(['success' => false]);
    }

    public function test_login_fails_validation_with_invalid_mobile(): void
    {
        $response = $this->postJson($this->base . '/auth/login', [
            'mobile' => '123',
            'password' => 'password123',
        ]);

        $response->assertStatus(200)
            ->assertJson(['success' => false]);
    }

    public function test_onboard_fails_validation_without_required_fields(): void
    {
        $response = $this->postJson($this->base . '/auth/onboard', []);

        $response->assertStatus(200)
            ->assertJson(['success' => false]);
    }

    public function test_wallet_requires_auth(): void
    {
        $this->getJson($this->base . '/my/wallet')
            ->assertStatus(401);
    }

    public function test_wallet_statements_require_auth(): void
    {
        $this->getJson($this->base . '/my/wallet/statements')
            ->assertStatus(401);
    }

    public function test_dashboard_requires_auth(): void
    {
        $this->getJson($this->base . '/my/dashboard')
            ->assertStatus(401);
    }

    public function test_profile_requires_auth(): void
    {
        $this->getJson($this->base . '/my/profile')
            ->assertStatus(401);
    }

    public function test_bookings_require_auth(): void
    {
        $this->getJson($this->base . '/my/bookings')
            ->assertStatus(401);
    }

    public function test_transport_lock_requires_auth(): void
    {
        $this->postJson($this->base . '/transport/lock', ['item_id' => 1])
            ->assertStatus(401);
    }

    public function test_hotel_hold_requires_auth(): void
    {
        $this->postJson($this->base . '/hotels/hold', ['room_type_id' => 1])
            ->assertStatus(401);
    }

    public function test_referred_property_store_requires_auth(): void
    {
        $this->postJson($this->base . '/my/referred-properties', [])
            ->assertStatus(401);
    }

    public function test_login_success_with_seeded_active_agent(): void
    {
        try {
            \Illuminate\Support\Facades\DB::connection()->getPdo();

            $agent = Agent::create([
                'name' => 'Test Agent',
                'email' => 'agent.apitest+' . uniqid() . '@example.com',
                'mobile' => '017' . random_int(10000000, 99999999),
                'password' => Hash::make('password123'),
                'status' => 1,
            ]);
        } catch (\Throwable $e) {
            $this->markTestSkipped('Database/agents table not available for seeded login test: ' . $e->getMessage());

            return;
        }

        try {
            $response = $this->postJson($this->base . '/auth/login', [
                'mobile' => $agent->mobile,
                'password' => 'password123',
            ]);

            $response->assertStatus(200)
                ->assertJson(['success' => true])
                ->assertJsonStructure(['success', 'message', 'token', 'user']);
        } finally {
            $agent->forceDelete();
        }
    }
}
