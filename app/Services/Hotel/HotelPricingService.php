<?php

namespace App\Services\Hotel;

use App\Models\HotelRoomType;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Computes per-stay totals from nightly rates + global VAT/charge options.
 *
 * VAT base:
 * - Default: service charge only (platform rule).
 * - Merchant-level: when `vat_applicable_to === customer`, VAT on room total
 *   (same as transport ApiOrder / merchant fare VAT).
 * - When applicable to merchant/vendor: not billed to the customer.
 *
 * For module-synced room types (`code` = mod_hr_{hotel_rooms.id}), each night uses
 * `hotel_rooms` prices: peak_price on peak **weekdays** (from `hotel_peak_day_rules`
 * by hotel country + supplier, then global "All"), off_peak_price on other days,
 * with base_price as fallback — matching the admin Peak Day Rules + room columns.
 */
final class HotelPricingService
{
    /**
     * @return array{
     *     nights: int,
     *     room_subtotal: float,
     *     vat_amount: float,
     *     charge_amount: float,
     *     total: float,
     *     currency: string,
     *     vat_base: string,
     *     vat_applicable_to: string,
     *     lines: list<array{date: string, rate: float}>
     * }
     */
    public function quoteStay(
        HotelRoomType $roomType,
        Carbon $checkIn,
        Carbon $checkOut,
        int $adults,
        int $children,
        ?string $vatApplicableTo = null,
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
        $chargeAmount = round($roomSubtotal * ($chargePct / 100), 2);

        $applicableTo = $this->normalizeVatApplicableTo(
            $vatApplicableTo ?? $this->resolveMerchantVatApplicableTo($roomType)
        );
        [$vatAmount, $vatBase] = $this->calculateVat($roomSubtotal, $chargeAmount, $vatPct, $applicableTo);
        $total = round($roomSubtotal + $chargeAmount + $vatAmount, 2);

        return [
            'nights' => $nights,
            'room_subtotal' => round($roomSubtotal, 2),
            'vat_percent' => $vatPct,
            'charge_percent' => $chargePct,
            'vat_amount' => $vatAmount,
            'charge_amount' => $chargeAmount,
            'total' => $total,
            'currency' => $currency,
            'vat_base' => $vatBase,
            'vat_applicable_to' => $applicableTo,
            'lines' => $lines,
            'adults' => $adults,
            'children' => $children,
        ];
    }

    /**
     * @return array{0: float, 1: string} [vatAmount, vatBase]
     */
    private function calculateVat(
        float $roomSubtotal,
        float $chargeAmount,
        float $vatPct,
        string $applicableTo,
    ): array {
        // Merchant/vendor absorbs VAT — not billed to the customer.
        if (in_array($applicableTo, ['merchant', 'vendor'], true)) {
            return [0.0, 'none'];
        }

        // Merchant-level config: VAT on room total (fare).
        if ($applicableTo === 'customer') {
            return [round($roomSubtotal * ($vatPct / 100), 2), 'total'];
        }

        // Default: VAT on service charge only.
        return [round($chargeAmount * ($vatPct / 100), 2), 'charge'];
    }

    private function normalizeVatApplicableTo(?string $value): string
    {
        $v = strtolower(trim((string) $value));
        if (in_array($v, ['customer', 'merchant', 'vendor'], true)) {
            return $v;
        }

        // No merchant-level VAT config → default base is service charge.
        return 'default';
    }

    private function resolveMerchantVatApplicableTo(HotelRoomType $roomType): ?string
    {
        try {
            if (! Schema::hasTable('hotels') || ! Schema::hasColumn('hotels', 'merchant_id')) {
                return null;
            }
            $merchantId = DB::table('hotels')->where('id', (int) $roomType->hotel_id)->value('merchant_id');
            if (! $merchantId || ! Schema::hasTable('merchants')) {
                return null;
            }
            if (! Schema::hasColumn('merchants', 'vat_applicable_to')) {
                return null;
            }
            $raw = DB::table('merchants')->where('id', (int) $merchantId)->value('vat_applicable_to');

            return $raw !== null ? (string) $raw : null;
        } catch (\Throwable) {
            return null;
        }
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
