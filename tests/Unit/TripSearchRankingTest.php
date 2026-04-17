<?php

namespace Tests\Unit;

use App\Services\Search\TripSearchRanking;
use PHPUnit\Framework\TestCase;

class TripSearchRankingTest extends TestCase
{
    public function test_availability_ratio_uses_seats_when_present(): void
    {
        $r = TripSearchRanking::availabilityRatio([
            'total_seats' => 10,
            'seat_available' => 3,
            'total_cabins' => 5,
            'cabin_available' => 5,
        ]);
        $this->assertEqualsWithDelta(0.3, $r, 0.001);
    }

    public function test_sort_merged_orders_by_score(): void
    {
        $items = [
            ['row' => ['trip_id' => 1], 'score' => 0.5, 'source' => 'internal'],
            ['row' => ['trip_id' => 2], 'score' => 2.0, 'source' => 'partner'],
            ['row' => ['trip_id' => 3], 'score' => 1.0, 'source' => 'internal'],
        ];
        $sorted = TripSearchRanking::sortMerged($items, 10);
        $this->assertSame(2, $sorted[0]['trip_id']);
        $this->assertSame(3, $sorted[1]['trip_id']);
        $this->assertSame(1, $sorted[2]['trip_id']);
    }

    public function test_norm_opensearch_score_is_bounded(): void
    {
        $this->assertLessThanOrEqual(1.0, TripSearchRanking::normOpensearchScore(100.0));
        $this->assertEquals(0.0, TripSearchRanking::normOpensearchScore(0.0));
    }
}
