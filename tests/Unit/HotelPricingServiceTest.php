<?php

namespace Tests\Unit;

use App\Models\HotelRoomType;
use App\Services\Hotel\HotelPricingService;
use Carbon\Carbon;
use Tests\TestCase;

class HotelPricingServiceTest extends TestCase
{
    public function test_quote_stay_single_night(): void
    {
        $svc = new HotelPricingService;
        $rt = new HotelRoomType([
            'base_price_per_night' => 1000,
            'currency' => 'BDT',
        ]);
        $q = $svc->quoteStay(
            $rt,
            Carbon::parse('2026-06-10'),
            Carbon::parse('2026-06-11'),
            2,
            0,
        );
        $this->assertSame(1, $q['nights']);
        $this->assertEquals(1000.0, (float) $q['room_subtotal']);
    }

    public function test_quote_stay_cross_month(): void
    {
        $svc = new HotelPricingService;
        $rt = new HotelRoomType([
            'base_price_per_night' => 500,
            'currency' => 'BDT',
        ]);
        $q = $svc->quoteStay(
            $rt,
            Carbon::parse('2026-06-29'),
            Carbon::parse('2026-07-02'),
            1,
            0,
        );
        $this->assertSame(3, $q['nights']);
        $this->assertEquals(1500.0, (float) $q['room_subtotal']);
        $this->assertCount(3, $q['lines']);
    }
}
