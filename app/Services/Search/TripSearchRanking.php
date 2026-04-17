<?php

namespace App\Services\Search;

/**
 * Deterministic merge ranking for internal + partner trip rows.
 * Weights are configured in config/trip_search.php under `ranking`.
 */
final class TripSearchRanking
{
    public static function availabilityRatio(array $row): float
    {
        $hasSeats = (int) ($row['total_seats'] ?? 0) > 0;
        if ($hasSeats) {
            $total = max(1, (int) ($row['total_seats'] ?? 1));
            $avail = max(0, (int) ($row['seat_available'] ?? 0));

            return min(1.0, $avail / $total);
        }
        $total = max(1, (int) ($row['total_cabins'] ?? 1));
        $avail = max(0, (int) ($row['cabin_available'] ?? 0));

        return min(1.0, $avail / $total);
    }

    public static function departureBoost(string $leavingAt, float $windowHours): float
    {
        $t = strtotime($leavingAt) ?: 0;
        if ($t <= 0) {
            return 0.0;
        }
        $hours = ($t - time()) / 3600.0;
        if ($hours < 0 || $hours > $windowHours) {
            return 0.0;
        }

        return 1.0 - ($hours / max(0.001, $windowHours));
    }

    public static function normOpensearchScore(float $raw): float
    {
        if ($raw <= 0) {
            return 0.0;
        }

        return 1.0 - exp(-min($raw, 20.0));
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public static function internalScore(array $row, float $opensearchScore): float
    {
        $cfg = config('trip_search.ranking', []);
        $wOs = (float) ($cfg['weight_opensearch_score'] ?? 1.0);
        $wAvail = (float) ($cfg['weight_availability'] ?? 0.15);
        $wDep = (float) ($cfg['weight_departure_soon'] ?? 0.05);
        $winH = (float) ($cfg['departure_window_hours'] ?? 36.0);

        $leaving = (string) ($row['leaving_at'] ?? '');

        return $wOs * self::normOpensearchScore($opensearchScore)
            + $wAvail * self::availabilityRatio($row)
            + $wDep * self::departureBoost($leaving, $winH);
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public static function partnerScore(array $row): float
    {
        $cfg = config('trip_search.ranking', []);
        $base = (float) ($cfg['partner_base_score'] ?? 0.85);
        $wAvail = (float) ($cfg['weight_availability'] ?? 0.15);
        $wDep = (float) ($cfg['weight_departure_soon'] ?? 0.05);
        $winH = (float) ($cfg['departure_window_hours'] ?? 36.0);
        $leaving = (string) ($row['leaving_at'] ?? '');

        return $base
            + $wAvail * self::availabilityRatio($row)
            + $wDep * self::departureBoost($leaving, $winH);
    }

    /**
     * @param  list<array{row: array<string, mixed>, score: float, source: string}>  $items
     * @return list<array<string, mixed>>
     */
    public static function sortMerged(array $items, int $limit): array
    {
        usort($items, static function (array $a, array $b): int {
            return $b['score'] <=> $a['score'];
        });
        $out = [];
        foreach (array_slice($items, 0, $limit) as $item) {
            $row = $item['row'];
            unset($row['_search_meta']);

            $out[] = $row;
        }

        return $out;
    }
}
