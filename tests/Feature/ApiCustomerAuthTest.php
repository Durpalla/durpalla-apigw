<?php

namespace Tests\Feature;

use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiCustomerAuthTest extends TestCase
{
    use RefreshDatabase;

    private string $base = '/api/v1/customer/auth';

    public function test_register_returns_201_with_customer_and_token(): void
    {
        $response = $this->postJson($this->base . '/register', [
            'name' => 'Test Customer',
            'email' => 'customer@test.com',
            'mobile' => '01700000001',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'customer' => ['id', 'name', 'email', 'mobile'],
                    'token',
                    'token_type',
                ],
            ])
            ->assertJson(['success' => true, 'data' => ['token_type' => 'Bearer']]);
    }

    public function test_register_fails_validation_without_required_fields(): void
    {
        $response = $this->postJson($this->base . '/register', []);

        $response->assertStatus(422);
    }

    public function test_login_returns_200_with_token(): void
    {
        Customer::factory()->create([
            'mobile' => '01700000002',
            'password' => 'secret',
        ]);

        $response = $this->postJson($this->base . '/login', [
            'mobile' => '01700000002',
            'password' => 'secret',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['success', 'message', 'data' => ['customer', 'token', 'token_type']])
            ->assertJson(['success' => true]);
    }

    public function test_login_with_pin_returns_200(): void
    {
        Customer::factory()->create([
            'mobile' => '01700000003',
            'password' => '1234',
        ]);

        $response = $this->postJson($this->base . '/login', [
            'mobile' => '01700000003',
            'pin' => '1234',
        ]);

        $response->assertStatus(200)->assertJson(['success' => true]);
    }

    public function test_login_returns_401_for_invalid_credentials(): void
    {
        $response = $this->postJson($this->base . '/login', [
            'mobile' => '01700000099',
            'password' => 'wrong',
        ]);

        $response->assertStatus(401)->assertJson(['success' => false]);
    }

    public function test_me_returns_401_without_token(): void
    {
        $response = $this->getJson($this->base . '/me');

        $response->assertStatus(401);
    }

    public function test_me_returns_200_with_customer_when_authenticated(): void
    {
        $customer = Customer::factory()->create();
        $token = $customer->createToken('customer-api')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson($this->base . '/me');

        $response->assertStatus(200)
            ->assertJsonStructure(['success', 'data' => ['id', 'name', 'email', 'mobile', 'created_at']])
            ->assertJson(['success' => true, 'data' => ['id' => $customer->id]]);
    }

    public function test_logout_returns_401_without_token(): void
    {
        $response = $this->postJson($this->base . '/logout');

        $response->assertStatus(401);
    }

    public function test_logout_returns_200_when_authenticated(): void
    {
        $customer = Customer::factory()->create();
        $token = $customer->createToken('customer-api')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson($this->base . '/logout');

        $response->assertStatus(200)->assertJson(['success' => true]);
    }
}
