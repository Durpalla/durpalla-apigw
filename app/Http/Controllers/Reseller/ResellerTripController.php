<?php

namespace App\Http\Controllers\Reseller;

use App\Constants\AppConst;
use App\Http\Controllers\Controller;
use App\Models\ScheduleCabinMapping;
use App\Models\VehicleSchedule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Read-only discovery of Durpalla public-quota transport trips available to
 * resellers, with real-time availability derived from schedule_cabin_mappings.
 */
class ResellerTripController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'date' => 'nullable|date',
            'from' => 'nullable|integer',
            'to' => 'nullable|integer',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $query = VehicleSchedule::query()
            ->where('status', AppConst::SCHEDULE_ACTIVE)
            ->where('leaving_at', '>=', now())
            ->whereHas('mappings', fn ($q) => $this->availableMapping($q))
            ->with(['vehicle', 'startFrom', 'stopTo'])
            ->withCount(['mappings as available_items_count' => fn ($q) => $this->availableMapping($q)]);

        if (! empty($data['date'])) {
            $query->whereDate('schedule_date', $data['date']);
        }
        if (! empty($data['from'])) {
            $query->where('starting_point', $data['from']);
        }
        if (! empty($data['to'])) {
            $query->where('ending_point', $data['to']);
        }

        $perPage = (int) ($data['per_page'] ?? 20);
        $schedules = $query->orderBy('leaving_at')->paginate($perPage);

        $schedules->getCollection()->transform(fn ($s) => [
            'trip_id' => $s->id,
            'vehicle' => $s->vehicle->name ?? null,
            'from' => $s->startFrom->name ?? null,
            'to' => $s->stopTo->name ?? null,
            'schedule_date' => $s->schedule_date,
            'leaving_at' => $s->leaving_at,
            'available_items' => $s->available_items_count,
        ]);

        return response()->json(['success' => true, 'data' => $schedules]);
    }

    public function show(Request $request, VehicleSchedule $trip): JsonResponse
    {
        $items = ScheduleCabinMapping::where('schedule_id', $trip->id)
            ->where(fn ($q) => $this->availableMapping($q))
            ->with(['cabin', 'cabinType'])
            ->get()
            ->map(fn ($m) => [
                'item_id' => $m->id,
                'type' => $m->type,
                'cabin_no' => $m->cabin->cabin_no ?? null,
                'cabin_type' => $m->cabinType->name ?? null,
                'fare' => (float) $m->fare,
            ]);

        $trip->load(['vehicle', 'startFrom', 'stopTo']);

        return response()->json([
            'success' => true,
            'data' => [
                'trip_id' => $trip->id,
                'vehicle' => $trip->vehicle->name ?? null,
                'from' => $trip->startFrom->name ?? null,
                'to' => $trip->stopTo->name ?? null,
                'schedule_date' => $trip->schedule_date,
                'leaving_at' => $trip->leaving_at,
                'items' => $items,
            ],
        ]);
    }

    /**
     * Constrains a mappings query to sellable Durpalla public-quota inventory.
     */
    private function availableMapping($q)
    {
        return $q->where('ownership', AppConst::PARTY_DURPALLA)
            ->where('booked', 0)
            ->where('is_reserved', 0);
    }
}
