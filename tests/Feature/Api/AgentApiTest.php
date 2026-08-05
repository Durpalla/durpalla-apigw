<?php

namespace Tests\Feature\Api;

use App\Models\Agent;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
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
        $response = $this->postJson($this->base.'/auth/login', []);

        $response->assertStatus(200)
            ->assertJson(['success' => false]);
    }

    public function test_login_fails_validation_with_invalid_mobile(): void
    {
        $response = $this->postJson($this->base.'/auth/login', [
            'mobile' => '123',
            'password' => 'password123',
        ]);

        $response->assertStatus(200)
            ->assertJson(['success' => false]);
    }

    public function test_onboard_fails_validation_without_required_fields(): void
    {
        $response = $this->postJson($this->base.'/auth/onboard', []);

        $response->assertStatus(200)
            ->assertJson(['success' => false]);
    }

    public function test_wallet_requires_auth(): void
    {
        $this->getJson($this->base.'/my/wallet')
            ->assertStatus(401);
    }

    public function test_wallet_statements_require_auth(): void
    {
        $this->getJson($this->base.'/my/wallet/statements')
            ->assertStatus(401);
    }

    public function test_dashboard_requires_auth(): void
    {
        $this->getJson($this->base.'/my/dashboard')
            ->assertStatus(401);
    }

    public function test_profile_requires_auth(): void
    {
        $this->getJson($this->base.'/my/profile')
            ->assertStatus(401);
    }

    public function test_bookings_require_auth(): void
    {
        $this->getJson($this->base.'/my/bookings')
            ->assertStatus(401);
    }

    public function test_transport_lock_requires_auth(): void
    {
        $this->postJson($this->base.'/transport/lock', ['item_id' => 1])
            ->assertStatus(401);
    }

    public function test_hotel_hold_requires_auth(): void
    {
        $this->postJson($this->base.'/hotels/hold', ['room_type_id' => 1])
            ->assertStatus(401);
    }

    public function test_referred_property_store_requires_auth(): void
    {
        $this->postJson($this->base.'/my/referred-properties', [])
            ->assertStatus(401);
    }

    public function test_login_success_with_seeded_active_agent(): void
    {
        $this->ensureLoginSchema();
        $agent = Agent::create([
            'name' => 'Test Agent',
            'email' => 'agent.apitest+'.uniqid().'@example.com',
            'mobile' => '017'.random_int(10000000, 99999999),
            'password' => Hash::make('password123'),
            'status' => 1,
        ]);

        try {
            $response = $this->postJson($this->base.'/auth/login', [
                'mobile' => $agent->mobile,
                'password' => 'password123',
            ]);

            $response->assertStatus(200)
                ->assertJson(['success' => true])
                ->assertJsonStructure(['success', 'message', 'token', 'user']);
        } finally {
            DB::table('personal_access_tokens')
                ->where('tokenable_type', Agent::class)
                ->where('tokenable_id', $agent->id)
                ->delete();
            DB::table('agents')->where('id', $agent->id)->delete();
        }
    }

    private function ensureLoginSchema(): void
    {
        if (! Schema::hasTable('agents')) {
            Schema::create('agents', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('email')->nullable()->unique();
                $table->string('mobile')->unique();
                $table->string('password');
                $table->unsignedTinyInteger('status')->default(1);
                $table->string('device_id')->nullable();
                $table->string('profile_pic')->nullable();
                $table->rememberToken();
                $table->softDeletes();
                $table->timestamps();
            });
        }
        if (! Schema::hasTable('user_metas')) {
            Schema::create('user_metas', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id')->index();
                $table->string('nid_no')->nullable();
                $table->string('city')->nullable();
                $table->text('address')->nullable();
                $table->timestamps();
            });
        }
        if (! Schema::hasTable('agent_incentives')) {
            Schema::create('agent_incentives', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('agent_id')->index();
                $table->decimal('incentive', 10, 4)->default(0);
                $table->string('incentive_type')->default('percent');
                $table->timestamps();
            });
        }
        if (! Schema::hasTable('personal_access_tokens')) {
            Schema::create('personal_access_tokens', function (Blueprint $table) {
                $table->id();
                $table->morphs('tokenable');
                $table->text('name');
                $table->string('token', 64)->unique();
                $table->text('abilities')->nullable();
                $table->timestamp('last_used_at')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->timestamps();
            });
        }
    }
}
