<?php

namespace App\Services\Search;

use App\Constants\AppConst;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Minimal OpenSearch HTTP client (no extra Composer deps).
 */
class OpenSearchTripClient
{
    public function isConfigured(): bool
    {
        $base = (string) config('trip_search.opensearch.base_url', '');

        return $base !== '' && (bool) config('trip_search.opensearch.enabled', false);
    }

    public function ensureIndex(): bool
    {
        if (! $this->isConfigured()) {
            return false;
        }
        $index = $this->indexName();
        $head = $this->http()
            ->withHeaders(['Content-Type' => 'application/json'])
            ->head($this->url("/{$index}"));

        if ($head->successful()) {
            return true;
        }

        $body = TripScheduleIndexDocument::indexMapping();
        $put = $this->http()
            ->withHeaders(['Content-Type' => 'application/json'])
            ->put($this->url("/{$index}"), $body);

        if (! $put->successful()) {
            Log::warning('OpenSearch ensureIndex failed', ['status' => $put->status(), 'body' => $put->body()]);

            return false;
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $document
     */
    public function indexDocument(int $scheduleId, array $document): bool
    {
        if (! $this->isConfigured()) {
            return false;
        }
        $index = $this->indexName();
        $res = $this->http()
            ->withHeaders(['Content-Type' => 'application/json'])
            ->put($this->url("/{$index}/_doc/{$scheduleId}"), $document);

        if (! $res->successful()) {
            Log::warning('OpenSearch indexDocument failed', ['id' => $scheduleId, 'status' => $res->status(), 'body' => $res->body()]);

            return false;
        }

        return true;
    }

    public function deleteDocument(int $scheduleId): void
    {
        if (! $this->isConfigured()) {
            return;
        }
        $index = $this->indexName();
        $this->http()->delete($this->url("/{$index}/_doc/{$scheduleId}"));
    }

    /**
     * @param  array<string, mixed>  $filters  trip_date Y-m-d, trip_from, trip_to, type (vehicle_type)
     * @return array{ids: list<int>, scores: array<int, float>}
     */
    public function searchOrderedIds(array $filters, int $limit): array
    {
        if (! $this->isConfigured()) {
            return ['ids' => [], 'scores' => []];
        }

        $must = [];
        $filter = [
            ['term' => ['status' => strtolower(AppConst::SCHEDULE_ACTIVE)]],
            ['term' => ['schedule_date' => $filters['trip_date']]],
        ];

        if (! empty($filters['vehicle_type'])) {
            $filter[] = ['term' => ['vehicle_type' => strtolower((string) $filters['vehicle_type'])]];
        }

        $from = isset($filters['trip_from']) ? strtolower(trim((string) $filters['trip_from'])) : '';
        $to = isset($filters['trip_to']) ? strtolower(trim((string) $filters['trip_to'])) : '';
        if ($from !== '' && $to !== '') {
            $filter[] = ['term' => ['pair_slugs' => $from.'|'.$to]];
        }

        $shouldText = [];
        if ($from !== '' || $to !== '') {
            $hint = trim($from.' '.$to);
            if ($hint !== '') {
                $shouldText[] = [
                    'multi_match' => [
                        'query' => $hint,
                        'fields' => ['searchable_text^2', 'route_name', 'vehicle_name'],
                        'type' => 'best_fields',
                        'fuzziness' => 'AUTO',
                    ],
                ];
            }
        }

        $query = [
            'bool' => [
                'filter' => $filter,
            ],
        ];
        if ($shouldText !== []) {
            $query['bool']['should'] = $shouldText;
            $query['bool']['minimum_should_match'] = 0;
        }

        $body = [
            'size' => $limit,
            'query' => $query,
            'sort' => [
                ['_score' => ['order' => 'desc']],
                ['leaving_at' => ['order' => 'asc']],
            ],
        ];

        $index = $this->indexName();
        $res = $this->http()
            ->withHeaders(['Content-Type' => 'application/json'])
            ->post($this->url("/{$index}/_search"), $body);

        if (! $res->successful()) {
            Log::warning('OpenSearch search failed', ['status' => $res->status(), 'body' => $res->body()]);

            return ['ids' => [], 'scores' => []];
        }

        $json = $res->json();
        $hits = $json['hits']['hits'] ?? [];
        $ids = [];
        $scores = [];
        foreach ($hits as $hit) {
            $id = (int) ($hit['_source']['schedule_id'] ?? $hit['_id'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            $ids[] = $id;
            $scores[$id] = (float) ($hit['_score'] ?? 0.0);
        }

        return ['ids' => $ids, 'scores' => $scores];
    }

    private function indexName(): string
    {
        return (string) config('trip_search.opensearch.index', 'durpalla_trip_schedules');
    }

    private function url(string $path): string
    {
        $base = rtrim((string) config('trip_search.opensearch.base_url', ''), '/');

        return $base.$path;
    }

    private function http(): \Illuminate\Http\Client\PendingRequest
    {
        $req = Http::timeout((int) config('trip_search.opensearch.timeout', 5));
        $user = (string) config('trip_search.opensearch.username', '');
        $pass = (string) config('trip_search.opensearch.password', '');
        if ($user !== '' || $pass !== '') {
            $req = $req->withBasicAuth($user, $pass);
        }

        return $req;
    }
}
