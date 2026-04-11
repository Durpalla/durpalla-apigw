<?php

namespace Tests\Feature;

use Tests\TestCase;

class ApiSupportDownloadTest extends TestCase
{
    private string $base = '/api/v1';

    public function test_support_send_accepts_request(): void
    {
        $response = $this->postJson($this->base . '/support/send', [
            'name' => 'Test',
            'email' => 'test@example.com',
            'message' => 'Hello',
        ]);
        $response->assertStatus(200)->assertJsonStructure(['success']);
    }

    public function test_download_link_accepts_request(): void
    {
        $response = $this->postJson($this->base . '/download/link', [
            'mobile' => '01700000001',
        ]);
        $response->assertStatus(200)->assertJsonStructure(['success', 'message']);
    }

    public function test_download_link_fails_validation_without_mobile(): void
    {
        $response = $this->postJson($this->base . '/download/link', []);
        $response->assertStatus(200);
        $this->assertArrayHasKey('success', $response->json());
    }
}
