<?php

namespace App\Services\Hotel;

use App\Models\HotelRoomType;
use Carbon\Carbon;

/**
 * Computes per-stay totals from nightly base price + global VAT/charge options (parity with transport).
 */
final class HotelPricingService
{
    /**
     * @return array{nights: int, room_subtotal: float, vat_amount: float, charge_amount: float, total: float, currency: string, lines: list<array{date: string, rate: float}>}
     */
    public function quoteStay(
        HotelRoomType $roomType,
        Carbon $checkIn,
        Carbon $checkOut,
        int $adults,
        int $children,
    ): array {
        $checkOutDay = $checkOut->copy()->startOfDay();
        $checkInDay = $checkIn->copy()->startOfDay();
        if ($checkOutDay <= $checkInDay) {
            throw new \InvalidArgumentException('check_out must be after check_in');
        }

        $nights = (int) $checkInDay->diffInDays($checkOutDay);
        $base = (float) $roomType->base_price_per_night;
        $currency = $roomType->currency ?: 'BDT';

        $lines = [];
        $roomSubtotal = 0.0;
        $cursor = $checkInDay->copy();
        for ($i = 0; $i < $nights; $i++) {
            $lines[] = [
                'date' => $cursor->toDateString(),
                'rate' => round($base, 2),
            ];
            $roomSubtotal += $base;
            $cursor->addDay();
        }

        $vatPct = (float) abs((float) getOption('vat_amount', 0));
        $platform = 'android';
        $chargePct = (float) abs((float) getOption('service_charge_'.$platform, getOption('service_charge_web', 0)));

        $vatAmount = round($roomSubtotal * ($vatPct / 100), 2);
        $chargeAmount = round($roomSubtotal * ($chargePct / 100), 2);
        $total = round($roomSubtotal + $vatAmount + $chargeAmount, 2);

        return [
            'nights' => $nights,
            'room_subtotal' => round($roomSubtotal, 2),
            'vat_percent' => $vatPct,
            'charge_percent' => $chargePct,
            'vat_amount' => $vatAmount,
            'charge_amount' => $chargeAmount,
            'total' => $total,
            'currency' => $currency,
            'lines' => $lines,
            'adults' => $adults,
            'children' => $children,
        ];
    }
}
