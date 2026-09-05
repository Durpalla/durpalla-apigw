<?php

namespace Tests\Feature;

use Tests\TestCase;

class LocalizationApiTest extends TestCase
{
    private string $base = '/api/v1/localizations';

    public function test_index_lists_locales(): void
    {
        $response = $this->getJson($this->base);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'default_locale',
                    'bundled_locales',
                    'remote_locales',
                ],
            ]);
    }

    public function test_show_returns_en_dictionary(): void
    {
        $response = $this->getJson($this->base.'/en');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.locale', 'en')
            ->assertJsonStructure([
                'data' => ['locale', 'version', 'fallback_locale', 'translations'],
            ])
            ->assertHeader('Content-Language', 'en')
            ->assertHeader('ETag');
    }

    public function test_show_returns_bn_with_accept_language(): void
    {
        $response = $this->getJson($this->base.'/bn', [
            'Accept-Language' => 'bn-BD',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.locale', 'bn')
            ->assertHeader('Content-Language', 'bn');
    }

    public function test_unsupported_locale_returns_404(): void
    {
        $response = $this->getJson($this->base.'/xx');

        $response->assertNotFound()
            ->assertJsonPath('success', false);
    }

    public function test_api_message_uses_accept_language(): void
    {
        $response = $this->getJson($this->base.'/xx', [
            'Accept-Language' => 'bn',
        ]);

        $response->assertNotFound();
        $this->assertSame('Locale সমর্থিত নয়।', $response->json('message'));
    }

    public function test_api_message_falls_back_to_content_language(): void
    {
        $response = $this->withHeaders([
            'Accept-Language' => '',
            'Content-Language' => 'bn',
        ])->getJson($this->base.'/xx');

        $response->assertNotFound();
        $this->assertSame('Locale সমর্থিত নয়।', $response->json('message'));
    }

    public function test_api_message_defaults_to_english(): void
    {
        $response = $this->getJson($this->base.'/xx');

        $response->assertNotFound();
        $this->assertSame('Locale not supported.', $response->json('message'));
    }
}
