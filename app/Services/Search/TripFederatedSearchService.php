<?php

namespace App\Services\Search;

use App\Repository\ScheduleRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * OpenSearch-ordered trip search with optional partner merge and response cache (Redis/ElastiCache).
 */
final class TripFederatedSearchService
{
    public function __construct(
        private readonly ScheduleRepository $schedules,
        private readonly OpenSearchTripClient $openSearch,
        private readonly TripPartnerAggregator $partners,
    ) {}

    /**
     * @param  callable(object): array<string, mixed>  $formatTripList
     * @return list<array<string, mixed>>
     */
    public function search(Request $request, callable $formatTripList): array
    {
        $this->schedules->normalizeTripSearchRequest($request);

        $ttl = max(0, (int) config('trip_search.cache.ttl_seconds', 0));
        $limit = max(1, (int) config('trip_search.limits.default_result_limit', 10));
        $osLimit = max($limit, (int) config('trip_search.limits.opensearch_fetch_limit', 50));

        if ($ttl > 0) {
            return Cache::remember($this->cacheKey($request), $ttl, fn () => $this->searchUncached($request, $formatTripList, $limit, $osLimit));
        }

        return $this->searchUncached($request, $formatTripList, $limit, $osLimit);
    }

    /**
     * @param  callable(object): array<string, mixed>  $formatTripList
     * @return list<array<string, mixed>>
     */
    public function searchUncached(Request $request, callable $formatTripList, int $limit, int $osLimit): array
    {
        if ($this->shouldUseLegacyRepositoryOnly($request)) {
            return $this->mergeWithPartners(
                $this->legacyDbList($request, $formatTripList),
                $request,
                $limit,
                false
            );
        }

        if ($this->openSearch->isConfigured()) {
            $filters = $this->openSearchFilters($request);
            $result = $this->openSearch->searchOrderedIds($filters, $osLimit);
            $ids = $result['ids'];
            $scores = $result['scores'];
            if ($ids !== []) {
                $scoredInternals = $this->hydrateScoredInternals($ids, $scores, $formatTripList);
                if ($scoredInternals !== []) {
                    return $this->mergeWithPartners($scoredInternals, $request, $limit, true);
                }
            }
        }

        return $this->mergeWithPartners(
            $this->legacyDbList($request, $formatTripList),
            $request,
            $limit,
            false
        );
    }

    private function shouldUseLegacyRepositoryOnly(Request $request): bool
    {
        if ($request->filled('trip_id')) {
            return true;
        }
        if ($request->filled('vehicle_id')) {
            return true;
        }
        if ($request->filled('return_date') || $request->filled('return_trip_date')) {
            return true;
        }

        return false;
    }

    /**
     * @return array<string, mixed>
     */
    private function openSearchFilters(Request $request): array
    {
        $tripDate = $request->input('trip_date', date('Y-m-d'));
        if (is_string($tripDate)) {
            $tripDate = date('Y-m-d', strtotime($tripDate));
        }

        $type = $request->input('type', $request->input('service_type', $request->input('vehicle_type')));

        return [
            'trip_date' => $tripDate,
            'trip_from' => (string) $request->input('trip_from', ''),
            'trip_to' => (string) $request->input('trip_to', ''),
            'vehicle_type' => $type ? (string) $type : '',
        ];
    }

    /**
     * @param  list<int>  $ids
     * @param  array<int, float>  $scores
     * @param  callable(object): array<string, mixed>  $formatTripList
     * @return list<array{row: array<string, mixed>, score: float, source: string}>
     */
    private function hydrateScoredInternals(array $ids, array $scores, callable $formatTripList): array
    {
        $collection = $this->schedules->findSchedulesByIdsForTripList($ids);

        if ($collection->isEmpty()) {
            return [];
        }

        $blend = (float) config('trip_search.ranking.partner_internal_blend', 0.72);
        $merged = [];
        foreach ($collection as $trip) {
            $os = (float) ($scores[(int) $trip->id] ?? 0.0);
            $row = $formatTripList($trip);
            $score = TripSearchRanking::internalScore($row, $os) * $blend;
            $merged[] = ['row' => $row, 'score' => $score, 'source' => 'internal'];
        }

        return $merged;
    }

    /**
     * @param  list<array<string, mixed>>|list<array{row: array<string, mixed>, score: float, source: string}>  $internalRowsOrScored
     * @return list<array<string, mixed>>
     */
    private function mergeWithPartners(array $internalRowsOrScored, Request $request, int $limit, bool $internalAlreadyScored): array
    {
        $partnerRows = $this->partners->fetchPartnerRows($request);
        $blend = (float) config('trip_search.ranking.partner_internal_blend', 0.72);
        $items = [];
        if ($internalAlreadyScored) {
            foreach ($internalRowsOrScored as $it) {
                $items[] = $it;
            }
        } else {
            foreach ($internalRowsOrScored as $row) {
                $items[] = [
                    'row' => $row,
                    'score' => TripSearchRanking::internalScore($row, 1.0) * $blend,
                    'source' => 'internal',
                ];
            }
        }
        if ($partnerRows === []) {
            return TripSearchRanking::sortMerged($items, $limit);
        }

        foreach ($partnerRows as $row) {
            $items[] = [
                'row' => $row,
                'score' => TripSearchRanking::partnerScore($row) * (1.0 - $blend),
                'source' => 'partner',
            ];
        }

        return TripSearchRanking::sortMerged($items, $limit);
    }

    /**
     * @param  callable(object): array<string, mixed>  $formatTripList
     * @return list<array<string, mixed>>
     */
    private function legacyDbList(Request $request, callable $formatTripList): array
    {
        $results = $this->schedules->searchTrip($request)
            ->map(fn ($trip) => $formatTripList($trip));
        $out = [];
        $results->each(function ($item) use (&$out) {
            $out[] = $item;
        });

        return $out;
    }

    private function cacheKey(Request $request): string
    {
        $this->schedules->normalizeTripSearchRequest($request);
        $payload = $request->only([
            'trip_id', 'vehicle_id', 'trip_date', 'trip_from', 'trip_to', 'type', 'service_type', 'vehicle_type',
            'return_date', 'return_trip_date', 'launch_name',
        ]);

        return 'trip_search:'.sha1(json_encode($payload));
    }
}
