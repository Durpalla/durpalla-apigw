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
            'default',
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
            'default',
        );
        $this->assertSame(3, $q['nights']);
        $this->assertEquals(1500.0, (float) $q['room_subtotal']);
        $this->assertCount(3, $q['lines']);
    }

    public function test_default_vat_applies_to_service_charge_only(): void
    {
        $svc = new HotelPricingService;
        $rt = new HotelRoomType([
            'base_price_per_night' => 2500,
            'currency' => 'BDT',
        ]);
        $q = $svc->quoteStay(
            $rt,
            Carbon::parse('2026-06-10'),
            Carbon::parse('2026-06-11'),
            2,
            0,
            'default',
        );

        $sub = (float) $q['room_subtotal'];
        $charge = (float) $q['charge_amount'];
        $vat = (float) $q['vat_amount'];
        $vatPct = (float) $q['vat_percent'];
        $total = (float) $q['total'];

        $this->assertEquals(2500.0, $sub);
        $this->assertSame('charge', $q['vat_base']);
        $this->assertEquals(round($charge * ($vatPct / 100), 2), $vat);
        $this->assertEquals(round($sub + $charge + $vat, 2), $total);
        if ($vatPct > 0 && $charge <= 0) {
            $this->assertEquals(0.0, $vat);
        }
    }

    public function test_merchant_customer_vat_applies_to_room_total(): void
    {
        $svc = new HotelPricingService;
        $rt = new HotelRoomType([
            'base_price_per_night' => 2500,
            'currency' => 'BDT',
        ]);
        $q = $svc->quoteStay(
            $rt,
            Carbon::parse('2026-06-10'),
            Carbon::parse('2026-06-11'),
            2,
            0,
            'customer',
        );

        $sub = (float) $q['room_subtotal'];
        $charge = (float) $q['charge_amount'];
        $vat = (float) $q['vat_amount'];
        $vatPct = (float) $q['vat_percent'];
        $total = (float) $q['total'];

        $this->assertSame('total', $q['vat_base']);
        $this->assertEquals(round($sub * ($vatPct / 100), 2), $vat);
        $this->assertEquals(round($sub + $charge + $vat, 2), $total);
    }

    public function test_merchant_absorbs_vat_when_applicable_to_merchant(): void
    {
        $svc = new HotelPricingService;
        $rt = new HotelRoomType([
            'base_price_per_night' => 2500,
            'currency' => 'BDT',
        ]);
        $q = $svc->quoteStay(
            $rt,
            Carbon::parse('2026-06-10'),
            Carbon::parse('2026-06-11'),
            2,
            0,
            'merchant',
        );

        $this->assertSame('none', $q['vat_base']);
        $this->assertEquals(0.0, (float) $q['vat_amount']);
        $this->assertEquals(
            round((float) $q['room_subtotal'] + (float) $q['charge_amount'], 2),
            (float) $q['total'],
        );
    }
}
