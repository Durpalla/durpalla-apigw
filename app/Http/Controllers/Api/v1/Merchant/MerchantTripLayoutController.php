<?php

namespace App\Http\Controllers\Api\v1\Merchant;

use App\Constants\AppConst;
use App\Http\Controllers\Controller;
use App\Jobs\ScheduleCreatedJob;
use App\Models\BookingItem;
use App\Models\Cabin;
use App\Models\ScheduleCabinMapping;
use App\Models\SeatLayout\TripSeatInventory;
use App\Models\VehicleSchedule;
use App\Events\TripPublicCartItemEvent;
use App\Services\SeatLayout\SeatLayoutEngine;
use App\Services\Transport\TransportSeatReferenceResolver;
use App\Support\ResolvesMerchantOwner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class MerchantTripLayoutController extends Controller
{
    use ResolvesMerchantOwner;

    public function __construct(
        private readonly SeatLayoutEngine $seatLayoutEngine,
        private readonly TransportSeatReferenceResolver $seatResolver,
    ) {}

    private function getTrip(Request $request, int $tripId): VehicleSchedule
    {
        $ownerId = $this->merchantOwnerId($request);
        return VehicleSchedule::where('id', $tripId)
            ->where('merchant_id', $ownerId)
            ->firstOrFail();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, ScheduleCabinMapping>
     */
    private function fetchMappingsForLayout(VehicleSchedule $trip, string $type, ?string $floor)
    {
        $q = ScheduleCabinMapping::where('schedule_id', $trip->id)
            ->where('type', $type)
            ->with('cabin.cabinType');
        if ($floor !== null && $floor !== '') {
            $q->where('floor', $floor);
        }

        return $q->orderBy('cabin_row')->orderBy('cabin_position')->get();
    }

    private function seatState(ScheduleCabinMapping $m, array $bookedCabinIds): string
    {
        $cid = $m->cabin_id;
        $bookedByItem = false;
        foreach ($bookedCabinIds as $bid) {
            if ((string) $bid === (string) $cid) {
                $bookedByItem = true;
                break;
            }
        }
        $isBooked = $bookedByItem || (bool) $m->booked;
        if ($isBooked) return 'booked';
        if ((bool) $m->is_reserved) return 'reserved';
        if ((bool) $m->is_locked) return 'locked';
        return 'available';
    }

    /**
     * GET /merchant/trips/{tripId}/floors
     * Distinct deck/floor ids for layout filtering (same idea as supervisor app).
     */
    public function floors(Request $request, int $tripId): JsonResponse
    {
        $trip = $this->getTrip($request, $tripId);

        $floorValues = ScheduleCabinMapping::where('schedule_id', $trip->id)
            ->select('floor')->distinct()->orderBy('floor')->pluck('floor')->filter()->values()->all();

        $list = collect($floorValues)->map(function ($f, int $i) {
            $id = ($f !== null && $f !== '') ? (string) $f : 'F'.($i + 1);

            return [
                'id' => $id,
                'label' => ($f !== null && $f !== '') ? (string) $f : 'Floor '.($i + 1),
            ];
        })->values()->all();

        if ($list === []) {
            $list = [['id' => '', 'label' => 'Deck']];
        }

        return response()->json([
            'success' => true,
            'data' => [
                'floors' => $list,
            ],
        ]);
    }

    /**
     * GET /merchant/trips/{tripId}/layout/{type}?floor=F1
     * type: seat|cabin|sofa
     */
    public function layout(Request $request, int $tripId, string $type): JsonResponse
    {
        $trip = $this->getTrip($request, $tripId);
        $type = strtolower(trim($type));
        if (! in_array($type, ['seat', 'cabin', 'sofa'], true)) {
            return response()->json(['success' => false, 'message' => 'Invalid layout type.'], 422);
        }

        $floor = $request->query('floor');
        $mappings = $this->fetchMappingsForLayout($trip, $type, $floor);

        if ($mappings->isEmpty() && Cabin::where('vehicle_id', $trip->vehicle_id)->exists()) {
            (new ScheduleCreatedJob($trip))->handle();
            $mappings = $this->fetchMappingsForLayout($trip, $type, $floor);
        }

        $bookedCabinIds = BookingItem::where('trip_id', $trip->id)
            ->where('status', AppConst::BOOKING_ITEM_ACTIVE)
            ->pluck('cabin_id')
            ->unique()
            ->filter()
            ->all();

        $trip->loadMissing('vehicle');
        $vehicleType = strtolower(trim((string) ($trip->vehicle?->vehicle_type ?? '')));
        // Launch (and boat): same labels as before — class letter + cabin_no (e.g. E-A1). Bus: cabin_no only when set.
        $useLaunchStyleLabels = in_array($vehicleType, ['launch', 'boat'], true);

        $rows = [];
        $currentRow = null;
        $rowCells = [];

        foreach ($mappings as $m) {
            $cabin = $m->cabin;
            $label = 'S'.$m->id;
            if ($cabin) {
                if ($useLaunchStyleLabels) {
                    $label = ($cabin->cabinType?->letter ? $cabin->cabinType->letter.'-' : '').$cabin->cabin_no;
                } else {
                    $cabinNo = trim((string) ($cabin->cabin_no ?? ''));
                    $label = $cabinNo !== ''
                        ? $cabinNo
                        : (($cabin->cabinType?->letter ? $cabin->cabinType->letter.'-' : '').$cabin->cabin_no);
                }
            }
            $state = $this->seatState($m, $bookedCabinIds);

            if ($currentRow !== null && (string) $m->cabin_row !== (string) $currentRow) {
                $rows[] = $rowCells;
                $rowCells = [];
            }
            $currentRow = $m->cabin_row;

            $rowCells[] = [
                'mapping_id' => (string) $m->id,
                'cabin_id' => (string) $m->cabin_id,
                'label' => $label,
                'state' => $state,
                'floor' => $m->floor,
                'fare' => (float) ($m->fare ?? $cabin?->fare ?? 0),
                'cabin_row' => $m->cabin_row,
                'cabin_position' => $m->cabin_position,
            ];
        }
        if (! empty($rowCells)) {
            $rows[] = $rowCells;
        }

        $reserveSupported = true;
        $layoutSource = 'schedule_cabin_mapping';

        if ($rows === [] && $type === 'seat') {
            $v2 = $this->buildSeatLayoutV2Rows($trip);
            if ($v2['rows'] !== []) {
                $rows = $v2['rows'];
                $reserveSupported = $v2['reserve_supported'];
                $layoutSource = $v2['layout_source'];
            }
        }

        return response()->json([
            'success' => true,
            'data' => [
                'trip_id' => (string) $trip->id,
                'type' => $type,
                'floor' => $floor,
                'rows' => $rows,
                'reserve_supported' => $reserveSupported,
                'layout_source' => $layoutSource,
            ],
        ]);
    }

    /**
     * New seat-layout system (seat_layout seats + trip_seat_inventory), used when
     * legacy schedule_cabin_mappings were never created (common for buses configured only in the seat-layout UI).
     *
     * @return array{rows: list<list<array<string, mixed>>>, reserve_supported: bool, layout_source: string}
     */
    private function buildSeatLayoutV2Rows(VehicleSchedule $trip): array
    {
        $trip->loadMissing('vehicle');
        $vehicle = $trip->vehicle;
        if (! $vehicle || ($vehicle->source ?? 'local') !== 'local') {
            return ['rows' => [], 'reserve_supported' => false, 'layout_source' => 'seat_layout_v2'];
        }

        try {
            $this->seatLayoutEngine->initializeInventory($trip);
        } catch (\Throwable $e) {
            Log::warning('merchant_trip_layout_seat_v2_init_failed', [
                'schedule_id' => $trip->id,
                'message' => $e->getMessage(),
            ]);
        }

        try {
            $grid = $this->seatLayoutEngine->getLayoutForSchedule($trip);
        } catch (\Throwable $e) {
            Log::warning('merchant_trip_layout_seat_v2_grid_failed', [
                'schedule_id' => $trip->id,
                'message' => $e->getMessage(),
            ]);

            return ['rows' => [], 'reserve_supported' => false, 'layout_source' => 'seat_layout_v2'];
        }

        if ($grid === []) {
            return ['rows' => [], 'reserve_supported' => false, 'layout_source' => 'seat_layout_v2'];
        }

        $inventoryBySeat = TripSeatInventory::query()
            ->where('schedule_id', $trip->id)
            ->get()
            ->keyBy('seat_id');

        $rows = [];
        foreach ($grid as $rowSeats) {
            $cells = [];
            foreach ($rowSeats as $seat) {
                $seatId = (int) ($seat['id'] ?? 0);
                $inv = $inventoryBySeat->get($seatId);
                $state = strtolower((string) ($seat['status'] ?? 'available'));
                if ($state === 'blocked') {
                    $state = 'booked';
                }
                if (! in_array($state, ['available', 'booked', 'locked'], true)) {
                    $state = 'available';
                }

                $cells[] = [
                    'mapping_id' => $inv ? (string) $inv->id : (string) $seatId,
                    'cabin_id' => (string) $seatId,
                    'label' => (string) ($seat['seat_number'] ?? $seatId),
                    'state' => $state,
                    'floor' => null,
                    'fare' => (float) ($seat['price'] ?? 0),
                    'row' => $seat['row'] ?? null,
                    'column' => $seat['column'] ?? null,
                ];
            }
            if ($cells !== []) {
                $rows[] = $cells;
            }
        }

        return [
            'rows' => $rows,
            'reserve_supported' => false,
            'layout_source' => 'seat_layout_v2',
        ];
    }

    /**
     * POST /merchant/trips/{tripId}/reserve
     * Body: { mapping_id:int, reserved:boolean }
     */
    public function reserve(Request $request, int $tripId): JsonResponse
    {
        $trip = $this->getTrip($request, $tripId);
        $validated = $request->validate([
            'mapping_id' => ['required', 'integer', 'min:1'],
            'reserved' => ['required', 'boolean'],
        ]);

        $m = ScheduleCabinMapping::where('schedule_id', $trip->id)->findOrFail((int) $validated['mapping_id']);

        // Never allow reserving a locked item (race-safe UI + prevents overriding other clients).
        if ((int) $m->is_locked === 1) {
            return response()->json(['success' => false, 'message' => 'Already locked.'], 422);
        }

        // Do not allow reserving booked items
        $isBooked = (bool) $m->booked || BookingItem::where('trip_id', $trip->id)
            ->where('status', AppConst::BOOKING_ITEM_ACTIVE)
            ->where('cabin_id', $m->cabin_id)
            ->exists();
        if ($isBooked) {
            return response()->json(['success' => false, 'message' => 'Already booked.'], 422);
        }

        // OTA-only mode (overdue): cap how many items the merchant may keep blocked.
        if ((bool) $validated['reserved'] && ! (bool) $m->is_reserved) {
            app(\Modules\Saas\App\Services\SaasEntitlementService::class)
                ->assertCanReserveBlock($this->merchantOwnerId($request));
        }

        $m->is_reserved = (bool) $validated['reserved'];
        $m->save();

        $m->refresh();
        $m->loadMissing('cabin.cabinType', 'cabinType', 'schedule');
        // Public `trip.{id}` + `item.*` only (matches passenger / apigw; avoids duplicate presence `seat.*`).
        $uid = ($u = $request->user()) ? (int) $u->id : null;
        if ($m->is_reserved) {
            TripPublicCartItemEvent::broadcastSafely(TripPublicCartItemEvent::EVENT_RESERVED, (int) $trip->id, $m, $uid);
        } else {
            TripPublicCartItemEvent::broadcastSafely(TripPublicCartItemEvent::EVENT_RELEASED, (int) $trip->id, $m, $uid);
        }

        return response()->json([
            'success' => true,
            'message' => $m->is_reserved ? 'Reserved.' : 'Reservation removed.',
        ]);
    }
}

