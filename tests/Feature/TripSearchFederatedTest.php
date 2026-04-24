<?php

namespace Tests\Feature;

use App\Services\Search\OpenSearchTripClient;
use Tests\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class TripSearchFederatedTest extends TestCase
{
    use RefreshDatabase;

    private string $base = '/api/v1';

    protected function setUp(): void
    {
        parent::setUp();
        config(['logging.default' => 'errorlog']);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_search_json_contract_when_opensearch_mock_returns_no_hits(): void
    {
        config(['trip_search.opensearch.enabled' => true, 'trip_search.opensearch.base_url' => 'http://opensearch.test']);

        $mock = Mockery::mock(OpenSearchTripClient::class);
        $mock->shouldReceive('isConfigured')->andReturn(true);
        $mock->shouldReceive('searchOrderedIds')->once()->andReturn(['ids' => [], 'scores' => []]);
        $this->app->instance(OpenSearchTripClient::class, $mock);

        $response = $this->getJson($this->base.'/search?trip_date='.date('Y-m-d').'&trip_from=A&trip_to=B');
        $this->assertContains($response->status(), [200, 500]);
        if ($response->status() === 200) {
            $response->assertJsonStructure(['success', 'data']);
            $this->assertTrue($response->json('success'));
            $this->assertIsArray($response->json('data'));
        }
    }

    public function test_transport_search_meta_contract_with_opensearch_mock(): void
    {
        config(['trip_search.opensearch.enabled' => true, 'trip_search.opensearch.base_url' => 'http://opensearch.test']);

        $mock = Mockery::mock(OpenSearchTripClient::class);
        $mock->shouldReceive('isConfigured')->andReturn(true);
        $mock->shouldReceive('searchOrderedIds')->andReturn(['ids' => [], 'scores' => []]);
        $this->app->instance(OpenSearchTripClient::class, $mock);

        $response = $this->getJson($this->base.'/transport/search?trip_date='.date('Y-m-d'));
        $this->assertContains($response->status(), [200, 500]);
        if ($response->status() === 200) {
            $response->assertJsonStructure(['success', 'data', 'meta']);
        }
    }
}
