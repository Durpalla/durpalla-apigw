<?php

namespace Tests\Feature;

use Tests\TestCase;

class LocalizationApiTest extends TestCase
{
    private string $base = '/api/v1/localizations';

    public function test_index_lists_apps(): void
    {
        $response = $this->getJson($this->base);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'default_locale',
                    'apps',
                ],
            ]);
    }

    public function test_app_index_customer_app(): void
    {
        $response = $this->getJson($this->base.'/customer-app');

        $response->assertOk()
            ->assertJsonPath('data.code', 'customer-app')
            ->assertJsonPath('data.format', 'arb-flat');
    }

    public function test_customer_app_en_dictionary(): void
    {
        $response = $this->getJson($this->base.'/customer-app/en');

        $response->assertOk()
            ->assertJsonPath('data.app', 'customer-app')
            ->assertJsonPath('data.locale', 'en')
            ->assertJsonPath('data.format', 'arb-flat')
            ->assertJsonStructure([
                'data' => ['app', 'locale', 'version', 'fallback_locale', 'format', 'translations'],
            ])
            ->assertHeader('Content-Language', 'en')
            ->assertHeader('ETag');

        $this->assertArrayHasKey('home', $response->json('data.translations'));
    }

    public function test_web_merchant_combined_hi(): void
    {
        $response = $this->getJson($this->base.'/web-merchant/hi?combined=1');

        $response->assertOk()
            ->assertJsonPath('data.app', 'web-merchant')
            ->assertJsonPath('data.locale', 'hi')
            ->assertJsonStructure(['data' => ['translations' => ['common', 'nav']]]);
    }

    public function test_web_merchant_namespace(): void
    {
        $response = $this->getJson($this->base.'/web-merchant/en/common');

        $response->assertOk()
            ->assertJsonPath('data.namespace', 'common');
    }

    public function test_legacy_route_web_customer(): void
    {
        $response = $this->getJson($this->base.'/en');

        $response->assertOk()
            ->assertJsonPath('data.app', 'web-customer');
    }

    public function test_unsupported_app_returns_404(): void
    {
        $response = $this->getJson($this->base.'/unknown-app/en');

        $response->assertNotFound();
    }

    public function test_unsupported_locale_returns_404(): void
    {
        $response = $this->getJson($this->base.'/customer-app/xx');

        $response->assertNotFound()
            ->assertJsonPath('success', false);
    }

    public function test_api_message_uses_accept_language(): void
    {
        $response = $this->getJson($this->base.'/customer-app/xx', [
            'Accept-Language' => 'bn',
        ]);

        $response->assertNotFound();
        $this->assertSame('Locale সমর্থিত নয়।', $response->json('message'));
    }
}
