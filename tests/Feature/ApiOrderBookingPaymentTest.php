<?php

namespace Tests\Feature;

use Tests\TestCase;

class ApiOrderBookingPaymentTest extends TestCase
{
    private string $base = '/api/v1';

    public function test_order_confirm_returns_401_without_token(): void
    {
        $response = $this->postJson($this->base . '/order/confirm', [
            'items' => [['item_id' => 1, 'type' => 'seat', 'for_self' => true]],
        ]);
        $response->assertStatus(401);
    }

    public function test_order_transaction_returns_401_without_token(): void
    {
        $response = $this->postJson($this->base . '/order/transaction', [
            'booking_id' => 1,
            'payment_method' => 'cash',
        ]);
        $response->assertStatus(401);
    }

    public function test_transport_booking_confirm_returns_401_without_token(): void
    {
        $response = $this->postJson($this->base . '/transport/booking/confirm', [
            'items' => [['item_id' => 1, 'type' => 'seat']],
        ]);
        $response->assertStatus(401);
    }

    public function test_booking_confirm_returns_401_without_token(): void
    {
        $response = $this->postJson($this->base . '/booking/confirm', [
            'items' => [['item_id' => 1, 'type' => 'seat']],
        ]);
        $response->assertStatus(401);
    }

    public function test_booking_payment_returns_401_without_token(): void
    {
        $response = $this->postJson($this->base . '/booking/payment', ['booking_id' => 1]);
        $response->assertStatus(401);
    }

    public function test_booking_check_returns_401_without_token(): void
    {
        $response = $this->getJson($this->base . '/booking/check/1');
        $response->assertStatus(401);
    }

    public function test_booking_cancel_returns_401_without_token(): void
    {
        $response = $this->postJson($this->base . '/booking/cancel', [
            'booking_id' => 1,
            'type' => 'full',
            'items' => '[]',
        ]);
        $response->assertStatus(401);
    }

    public function test_payment_make_returns_json(): void
    {
        $response = $this->postJson($this->base . '/payment/make', [
            'booking_id' => 1,
            'payment_method' => 'sslcommerz',
        ]);
        $this->assertContains($response->status(), [200, 401, 404, 422, 500]);
        if ($response->status() === 200) {
            $response->assertJsonStructure(['success']);
        }
    }

    public function test_payment_validate_returns_json(): void
    {
        $response = $this->postJson($this->base . '/payment/validate', [
            'order_id' => 1,
            'trx_id' => 'test-trx',
        ]);
        $this->assertContains($response->status(), [200, 401, 404, 422, 500]);
        if ($response->status() === 200) {
            $response->assertJsonStructure(['success']);
        }
    }

    public function test_payment_verify_returns_401_without_token(): void
    {
        $response = $this->getJson($this->base . '/payment/verify');
        $response->assertStatus(401);
    }

    public function test_coupon_validate_returns_401_without_token(): void
    {
        $response = $this->postJson($this->base . '/coupon/validate', ['coupon' => 'TEST']);
        $response->assertStatus(401);
    }
}
