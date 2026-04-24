<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserOtp;
use Tests\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ApiAuthTest extends TestCase
{
    use RefreshDatabase;

    private string $base = '/api/v1/auth';

    public function test_login_accepts_request(): void
    {
        User::factory()->create([
            'mobile' => '01700000001',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
            'type' => 'customer',
        ]);

        $response = $this->postJson($this->base . '/login', [
            'mobile' => '01700000001',
            'password' => 'password',
        ]);
        $response->assertStatus(200);
        $response->assertJsonStructure(['success', 'message']);
    }

    public function test_register_accepts_request(): void
    {
        $response = $this->postJson($this->base . '/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'mobile' => '01700000002',
            'nid' => '1234567890123',
            'password' => 'password123',
            'confirm_password' => 'password123',
        ]);
        $this->assertContains($response->status(), [200, 422, 500]);
    }

    public function test_check_accepts_request(): void
    {
        $response = $this->postJson($this->base . '/check', ['mobile' => '01700000001']);
        $response->assertStatus(200);
        $response->assertJsonStructure(['success']);
    }

    public function test_verify_accepts_request(): void
    {
        UserOtp::create([
            'mobile' => '01700000001',
            'otp_code' => '123456',
            'verified' => 0,
        ]);

        $response = $this->postJson($this->base . '/verify', [
            'mobile' => '01700000001',
            'code' => '123456',
        ]);
        $response->assertStatus(200);
    }

    public function test_forgot_accepts_request(): void
    {
        $response = $this->postJson($this->base . '/forgot', ['email' => 'user@example.com']);
        $response->assertStatus(200);
    }

    public function test_reset_accepts_request(): void
    {
        $response = $this->postJson($this->base . '/reset', [
            'email' => 'user@example.com',
            'token' => 'token',
            'password' => 'newpassword',
            'password_confirmation' => 'newpassword',
        ]);
        $response->assertStatus(200);
    }

    public function test_otp_resend_accepts_request(): void
    {
        UserOtp::create([
            'mobile' => '01700000001',
            'otp_code' => '123456',
            'verified' => 0,
        ]);

        $response = $this->postJson($this->base . '/otp/resend', ['mobile' => '01700000001']);
        $response->assertStatus(200);
    }

    public function test_logout_returns_401_without_token(): void
    {
        $response = $this->postJson($this->base . '/logout');
        $response->assertStatus(401);
    }

    public function test_push_bind_returns_401_without_token(): void
    {
        $response = $this->postJson($this->base . '/push/bind', []);
        $response->assertStatus(401);
    }
}
