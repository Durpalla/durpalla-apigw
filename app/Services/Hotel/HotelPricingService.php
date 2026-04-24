<?php

namespace App\Services\Hotel;

use App\Models\HotelRoomType;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Computes per-stay totals from nightly rates + global VAT/charge options.
 *
 * For module-synced room types (`code` = mod_hr_{hotel_rooms.id}), each night uses
 * `hotel_rooms` prices: peak_price on peak **weekdays** (from `hotel_peak_day_rules`
 * by hotel country + supplier, then global "All"), off_peak_price on other days,
 * with base_price as fallback — matching the admin Peak Day Rules + room columns.
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
        $currency = $roomType->currency ?: 'BDT';

        $lines = [];
        $roomSubtotal = 0.0;
        $cursor = $checkInDay->copy();

        $mod = null;
        $peakWeekdays = [5, 6];
        if (preg_match('/^mod_hr_(\d+)$/', (string) $roomType->code, $m) && Schema::hasTable('hotel_rooms')) {
            $mod = DB::table('hotel_rooms')->where('id', (int) $m[1])->first();
            if ($mod !== null) {
                $peakWeekdays = $this->peakDayRules()->peakWeekdaysForHotel((int) $mod->hotel_id);
            }
        }

        for ($i = 0; $i < $nights; $i++) {
            if ($mod !== null) {
                $rate = $this->moduleHotelRoomRateForNight($mod, $cursor, $peakWeekdays);
                if ($rate <= 0) {
                    $rate = (float) $roomType->base_price_per_night;
                }
            } else {
                $rate = (float) $roomType->base_price_per_night;
            }
            $lines[] = [
                'date' => $cursor->toDateString(),
                'rate' => round($rate, 2),
            ];
            $roomSubtotal += $rate;
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

    /**
     * @param  object  $room  `hotel_rooms` row
     * @param  list<int>  $peakWeekdays  0=Sun..6=Sat
     */
    private function moduleHotelRoomRateForNight(object $room, Carbon $night, array $peakWeekdays): float
    {
        $dow = (int) $night->dayOfWeek;
        $isPeakDay = in_array($dow, $peakWeekdays, true);

        $pp = $room->peak_price ?? null;
        $op = $room->off_peak_price ?? null;
        $base = $room->base_price ?? null;

        if ($isPeakDay) {
            if ($pp !== null && $pp !== '' && (float) $pp > 0) {
                return (float) $pp;
            }
            if ($base !== null && $base !== '' && (float) $base >= 0) {
                return (float) $base;
            }

            return 0.0;
        }

        if ($op !== null && $op !== '' && (float) $op > 0) {
            return (float) $op;
        }
        if ($base !== null && $base !== '' && (float) $base >= 0) {
            return (float) $base;
        }

        return 0.0;
    }

    private function peakDayRules(): PeakDayRuleResolver
    {
        return app(PeakDayRuleResolver::class);
    }
}

