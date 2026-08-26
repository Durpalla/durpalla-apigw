<?php

namespace App\Http\Controllers\Api\v1\Merchant;

use App\Models\HotelInventory;
use App\Models\HotelRoomType;
use App\Services\Hotel\HotelInventoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use App\Models\Hotel;
use App\Models\HotelRoom;
use App\Models\RoomType;

class MerchantHotelAvailabilityController extends MerchantHotelBaseController
{
    public function __construct(
        private readonly HotelInventoryService $inventory,
    ) {}

    /**
     * GET /api/v1/merchant/hotels/{hotelId}/availability
     *
     * Sellable capacity from shared hotel_inventory (same SoT as apigw).
     */
    public function index(Request $request, int $hotelId): JsonResponse
    {
        $ownerId = $this->merchantOwnerId($request);
        $this->assertHotelAllowed($ownerId);

        $validated = $request->validate([
            'check_in' => ['required', 'date_format:Y-m-d'],
            'check_out' => ['required', 'date_format:Y-m-d'],
            'room_type_id' => ['nullable', 'integer'],
        ]);

        $hotel = Hotel::query()->where('merchant_id', $ownerId)->findOrFail($hotelId);

        $checkIn = Carbon::createFromFormat('Y-m-d', (string) $validated['check_in'])->startOfDay();
        $checkOut = Carbon::createFromFormat('Y-m-d', (string) $validated['check_out'])->startOfDay();
        if ($checkOut->lte($checkIn)) {
            return response()->json([
                'success' => false,
                'message' => 'check_out must be after check_in.',
            ], 422);
        }

        $nights = $checkIn->diffInDays($checkOut);
        $roomTypeId = (int) ($validated['room_type_id'] ?? 0);
        $dates = HotelInventoryService::nightDates($checkIn, $checkOut);
        if ($dates === []) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid stay range.',
            ], 422);
        }

        $hotelRooms = HotelRoom::query()
            ->where('hotel_id', $hotel->id)
            ->when($roomTypeId > 0, fn ($q) => $q->where('room_type_id', $roomTypeId))
            ->orderBy('id')
            ->get();

        $byModuleType = [];
        foreach ($hotelRooms as $hr) {
            $apiRt = $this->ensureApiRoomType($hr);
            foreach ($dates as $date) {
                $this->inventory->ensureInventoryRow($apiRt, $date);
            }

            $rows = HotelInventory::query()
                ->where('hotel_room_type_id', $apiRt->id)
                ->whereDate('night_date', '>=', $dates[0])
                ->whereDate('night_date', '<=', $dates[array_key_last($dates)])
                ->get();

            $minAvailable = null;
            $totalRooms = 0;
            foreach ($dates as $date) {
                $row = $rows->first(fn ($r) => Carbon::parse($r->night_date)->format('Y-m-d') === $date);
                $total = $row ? (int) $row->units_total : 0;
                $sold = $row ? (int) $row->units_sold : 0;
                $held = $row ? (int) $row->units_held : 0;
                $avail = max(0, $total - $sold - $held);
                $minAvailable = $minAvailable === null ? $avail : min($minAvailable, $avail);
                $totalRooms = max($totalRooms, $total);
            }

            $typeId = (int) $hr->room_type_id;
            if (! isset($byModuleType[$typeId])) {
                $byModuleType[$typeId] = [
                    'total_rooms' => 0,
                    'min_available' => 0,
                    'base_price' => (float) ($hr->base_price ?? 0),
                ];
            } else {
                $byModuleType[$typeId]['base_price'] = min(
                    $byModuleType[$typeId]['base_price'],
                    (float) ($hr->base_price ?? 0)
                );
            }
            $byModuleType[$typeId]['total_rooms'] += $totalRooms;
            $byModuleType[$typeId]['min_available'] += (int) ($minAvailable ?? 0);
        }

        $types = RoomType::query()
            ->whereIn('id', array_keys($byModuleType))
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'description']);

        $items = [];
        foreach ($types as $t) {
            $row = $byModuleType[(int) $t->id] ?? null;
            $items[] = [
                'room_type_id' => (int) $t->id,
                'room_type' => [
                    'id' => (int) $t->id,
                    'name' => (string) $t->name,
                    'code' => $t->code,
                    'description' => $t->description,
                ],
                'base_price' => (float) ($row['base_price'] ?? 0),
                'total_rooms' => (int) ($row['total_rooms'] ?? 0),
                'min_available' => (int) ($row['min_available'] ?? 0),
            ];
        }

        return response()->json([
            'success' => true,
            'data' => [
                'date_range' => [
                    'check_in' => $checkIn->format('Y-m-d'),
                    'check_out' => $checkOut->format('Y-m-d'),
                    'nights' => $nights,
                ],
                'items' => $items,
            ],
        ]);
    }

    private function ensureApiRoomType(HotelRoom $hotelRoom): HotelRoomType
    {
        $code = 'mod_hr_'.$hotelRoom->id;
        $title = trim((string) ($hotelRoom->name ?? ''));
        if ($title === '') {
            $title = 'Room '.$hotelRoom->id;
        }

        $payload = [
            'title' => $title,
            'max_occupancy' => max(1, (int) ($hotelRoom->max_occupancy ?? 2)),
            'base_price_per_night' => (float) ($hotelRoom->base_price ?? 0),
            'currency' => 'BDT',
            'status' => 1,
        ];
        if (Schema::hasColumn((new HotelRoomType)->getTable(), 'is_active')) {
            $payload['is_active'] = 1;
        }

        return HotelRoomType::query()->updateOrCreate(
            [
                'hotel_id' => (int) $hotelRoom->hotel_id,
                'code' => $code,
            ],
            $payload,
        );
    }
}
