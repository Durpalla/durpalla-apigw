<?php

namespace App\Services\Search;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Optional third-party trip search. Responses are cached per normalized query key.
 * Expects JSON: { "data": [ { ... same keys as formatTripList ... } ] } or [ {...} ].
 */
final class TripPartnerAggregator
{
    public function fetchPartnerRows(Request $request): array
    {
        if (! (bool) config('trip_search.partner.enabled', false)) {
            return [];
        }
        $url = trim((string) config('trip_search.partner.url', ''));
        if ($url === '') {
            return [];
        }

        $ttl = max(0, (int) config('trip_search.partner.cache_ttl_seconds', 60));
        $prefix = (string) config('trip_search.partner.cache_key_prefix', 'trip_partner:');
        $key = $prefix.sha1(json_encode($this->partnerQuerySignature($request)));

        if ($ttl > 0) {
            return Cache::remember($key, $ttl, fn () => $this->httpFetch($url, $request));
        }

        return $this->httpFetch($url, $request);
    }

    /**
     * @return array<string, mixed>
     */
    private function partnerQuerySignature(Request $request): array
    {
        return [
            'trip_date' => $request->input('trip_date'),
            'trip_from' => $request->input('trip_from'),
            'trip_to' => $request->input('trip_to'),
            'type' => $request->input('type', $request->input('service_type', $request->input('vehicle_type'))),
        ];
    }

    private function httpFetch(string $url, Request $request): array
    {
        try {
            $timeout = (int) config('trip_search.partner.timeout', 8);
            $res = Http::timeout($timeout)
                ->acceptJson()
                ->get($url, $this->partnerQuerySignature($request));
            if (! $res->successful()) {
                Log::debug('TripPartnerAggregator non-OK', ['status' => $res->status()]);

                return [];
            }
            $json = $res->json();
            if (! is_array($json)) {
                return [];
            }
            $rows = $json['data'] ?? $json;
            if (! is_array($rows)) {
                return [];
            }
            $out = [];
            foreach ($rows as $row) {
                if (is_array($row) && isset($row['trip_id'])) {
                    $out[] = $row;
                }
            }

            return $out;
        } catch (\Throwable $e) {
            Log::debug('TripPartnerAggregator failed', ['e' => $e->getMessage()]);

            return [];
        }
    }
}
