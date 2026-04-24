<?php

namespace App\Services\Hotel;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Resolves which weekdays (0=Sun..6=Sat) count as "peak" for a hotel, using
 * the `hotel_peak_day_rules` table (country + supplier) with the same priority as
 * the main app's `PeakDayRuleService`, plus a (null, null) "All" row, then
 * `config('hotel.default_peak_days')`.
 */
final class PeakDayRuleResolver
{
    private const TABLE = 'hotel_peak_day_rules';

    /**
     * @return list<int> Weekday numbers 0=Sunday … 6=Saturday
     */
    public function peakWeekdaysForHotel(int $hotelId): array
    {
        $default = $this->defaultPeakDays();
        if (! Schema::hasTable(self::TABLE)) {
            return $default;
        }

        $countryId = $this->countryIdForHotel($hotelId);
        $supplierId = $this->supplierIdForHotel($hotelId);

        foreach ($this->lookupPairs($countryId, $supplierId) as [$c, $s]) {
            $row = $this->findRuleRow($c, $s);
            $days = $this->decodePeakDays($row?->peak_days);
            if ($days !== null && $days !== []) {
                return $days;
            }
        }

        return $default;
    }

    /**
     * @return list<int>
     */
    private function defaultPeakDays(): array
    {
        $d = config('hotel.default_peak_days', [5, 6]);
        if (! is_array($d) || $d === []) {
            return [5, 6];
        }

        return array_values(array_map('intval', $d));
    }

    /**
     * Same order as module PeakDayRuleService, plus (null,null) for global "All" rules.
     *
     * @return list<array{0: ?int, 1: ?int}>
     */
    private function lookupPairs(?int $countryId, ?int $supplierId): array
    {
        $pairs = [];
        if ($countryId !== null && $supplierId !== null) {
            $pairs[] = [$countryId, $supplierId];
        }
        if ($countryId !== null) {
            $pairs[] = [$countryId, null];
        }
        if ($supplierId !== null) {
            $pairs[] = [null, $supplierId];
        }
        $pairs[] = [null, null];

        return $pairs;
    }

    private function findRuleRow(?int $countryId, ?int $supplierId): ?object
    {
        $q = DB::table(self::TABLE);
        if ($countryId === null) {
            $q->whereNull('country_id');
        } else {
            $q->where('country_id', $countryId);
        }
        if ($supplierId === null) {
            $q->whereNull('supplier_id');
        } else {
            $q->where('supplier_id', $supplierId);
        }

        return $q->first();
    }

    /**
     * @return list<int>|null
     */
    private function decodePeakDays(mixed $raw): ?array
    {
        if ($raw === null) {
            return null;
        }
        if (is_array($raw)) {
            $out = array_map('intval', $raw);

            return $out === [] ? null : array_values($out);
        }
        if (is_string($raw) && $raw !== '') {
            $d = json_decode($raw, true);
            if (is_array($d)) {
                $out = array_map('intval', $d);

                return $out === [] ? null : array_values($out);
            }
        }

        return null;
    }

    private function countryIdForHotel(int $hotelId): ?int
    {
        if (! Schema::hasTable('hotels')) {
            return null;
        }
        $h = DB::table('hotels')->where('id', $hotelId)->first();
        if ($h === null || empty($h->city_id)) {
            return null;
        }
        if (! Schema::hasTable('cities')) {
            return null;
        }
        $c = DB::table('cities')->where('id', $h->city_id)->first();
        if ($c === null) {
            return null;
        }
        if (! Schema::hasColumn('cities', 'country_id')) {
            return null;
        }
        $cid = $c->country_id ?? null;

        return $cid !== null && $cid !== '' ? (int) $cid : null;
    }

    private function supplierIdForHotel(int $hotelId): ?int
    {
        if (! Schema::hasTable('hotels')) {
            return null;
        }
        $h = DB::table('hotels')->where('id', $hotelId)->first();
        if ($h === null) {
            return null;
        }
        if (Schema::hasColumn('hotels', 'supplier_id') && $h->supplier_id !== null && $h->supplier_id !== '') {
            return (int) $h->supplier_id;
        }

        return null;
    }
}
