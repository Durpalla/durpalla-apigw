<?php

namespace App\Services\Transport;

use App\Models\ScheduleCabinMapping;
use App\Models\VehicleSchedule;
use Illuminate\Support\Collection;

/**
 * Resolves layout / label seat references to schedule_cabin_mappings for a trip.
 * Used by POST transport/lock (trip_id + seat_ids) and supervisor seat APIs.
 *
 * {@see self::supervisorAppSeatId()} must stay aligned with {@see \App\Http\Controllers\Api\v1\SupervisorApp\SupervisorAppLayoutController::buildLayout()}
 * seat `id` / `label` when no `floorId` query is used (and matches the mid part after a floor prefix, e.g. `F1-E-A1` → `E-A1`).
 */
class TransportSeatReferenceResolver
{
    /**
     * Canonical seat string for Pusher {@see \App\Events\SupervisorAppTripSeatEvent} payloads so supervisor / merchant
     * clients can match the same identifiers as layout APIs (letter + cabin_no, e.g. `D-2`), not raw mapping ids.
     */
    public function supervisorAppSeatId(ScheduleCabinMapping $m): string
    {
        $m->loadMissing('cabin.cabinType');
        $cabin = $m->cabin;
        if (! $cabin) {
            return 'S'.$m->id;
        }
        $letter = $cabin->cabinType?->letter;
        $no = trim((string) ($cabin->cabin_no ?? ''));

        return ($letter ? $letter.'-' : '').$no;
    }

    /**
     * @return list<string>
     */
    public function mappingSeatIdVariants(ScheduleCabinMapping $m): array
    {
        $cabin = $m->cabin;
        if (! $cabin) {
            return ['S'.$m->id, (string) $m->id, (string) $m->cabin_id];
        }
        $letter = $cabin->cabinType?->letter;
        $no = trim((string) ($cabin->cabin_no ?? ''));
        $mid = ($letter ? $letter.'-' : '').$no;
        $variants = [];
        $f = $m->floor;
        if ($f !== null && $f !== '') {
            $variants[] = $f.'-'.$mid;
        }
        $variants[] = $mid;
        // Layout / Pusher sometimes use `EB2` (no hyphen) while canonical is `E-B2`.
        if ($letter !== null && $letter !== '' && $no !== '') {
            $variants[] = strtoupper((string) $letter).$no;
        }
        // Merchant layout falls back to S{mapping_id} when cabin_no is empty.
        $variants[] = 'S'.$m->id;
        $variants[] = (string) $m->cabin_id;
        $variants[] = (string) $m->id;

        return array_values(array_unique($variants));
    }

    public function resolveMapping(VehicleSchedule $trip, string $seatId): ?ScheduleCabinMapping
    {
        $seatId = trim($seatId);
        if ($seatId !== '' && $seatId[0] === '#') {
            $seatId = trim(substr($seatId, 1));
        }
        /** @var Collection<int, ScheduleCabinMapping> $mappings */
        $mappings = ScheduleCabinMapping::where('schedule_id', $trip->id)->with('cabin.cabinType')->get();

        foreach ($mappings as $m) {
            foreach ($this->mappingSeatIdVariants($m) as $v) {
                if ($seatId === $v) {
                    return $m;
                }
            }
        }

        $floorIds = ScheduleCabinMapping::where('schedule_id', $trip->id)->pluck('floor')->unique()->filter()->values()->all();
        foreach ($mappings as $m) {
            $cabin = $m->cabin;
            if (! $cabin) {
                continue;
            }
            $letter = $cabin->cabinType?->letter;
            $mid = ($letter ? $letter.'-' : '').$cabin->cabin_no;
            $suffix = '-'.$mid;
            if (! str_ends_with($seatId, $suffix) || $seatId === $mid) {
                continue;
            }
            $prefix = substr($seatId, 0, -strlen($suffix));
            if ($prefix === '' || $prefix === false) {
                continue;
            }
            if ($m->floor === $prefix || $m->floor === null || $m->floor === '' || in_array($prefix, $floorIds, true)) {
                return $m;
            }
        }

        return null;
    }
}
