<?php

namespace App\Http\Controllers\Api\v1\Merchant;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Hotel\Entities\Hotel;
use Modules\Hotel\Entities\HotelRoom;
use Modules\Hotel\Entities\HotelRoomUnit;
use Modules\Hotel\Services\HotelInventorySyncService;

class MerchantHotelRoomController extends MerchantHotelBaseController
{
    public function __construct(private HotelInventorySyncService $inventorySync)
    {
    }
    public function index(Request $request, int $hotelId): JsonResponse
    {
        $ownerId = $this->merchantOwnerId($request);
        $this->assertHotelAllowed($ownerId);

        $hotel = Hotel::query()->where('merchant_id', $ownerId)->findOrFail($hotelId);
        $rooms = HotelRoom::query()
            ->where('hotel_id', $hotel->id)
            ->with(['roomType', 'roomUnits', 'facilities', 'images'])
            ->orderByDesc('id')
            ->get();

        return response()->json(['success' => true, 'data' => $rooms]);
    }

    public function store(Request $request, int $hotelId): JsonResponse
    {
        $ownerId = $this->merchantOwnerId($request);
        $this->assertHotelAllowed($ownerId);
        $this->saasEntitlements()->assertCanCreateHotelRooms($ownerId, 1);

        $hotel = Hotel::query()->where('merchant_id', $ownerId)->findOrFail($hotelId);

        $validated = $request->validate([
            'room_type_id' => ['required', 'integer', 'exists:room_types,id'],
            'name' => ['required', 'string', 'max:191'],
            'max_adults' => ['nullable', 'integer', 'min:0', 'max:20'],
            'max_children' => ['nullable', 'integer', 'min:0', 'max:20'],
            'max_occupancy' => ['nullable', 'integer', 'min:1', 'max:20'],
            'base_price' => ['required', 'numeric', 'min:0'],
            'peak_price' => ['nullable', 'numeric', 'min:0'],
            'off_peak_price' => ['nullable', 'numeric', 'min:0'],
            'total_rooms' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'status' => ['nullable', 'integer', 'in:0,1,2'],
            'facility_ids' => ['nullable', 'array'],
            'facility_ids.*' => ['integer', 'exists:hotel_facilities,id'],
        ]);

        $facilityIds = $validated['facility_ids'] ?? [];
        unset($validated['facility_ids']);

        $room = HotelRoom::create(array_merge($validated, [
            'hotel_id' => $hotel->id,
            'created_by' => (int) auth()->id(),
            'updated_by' => (int) auth()->id(),
            'status' => $validated['status'] ?? 1,
            'max_adults' => $validated['max_adults'] ?? 2,
            'max_children' => $validated['max_children'] ?? 0,
            'max_occupancy' => $validated['max_occupancy'] ?? max(1, (int) ($validated['max_adults'] ?? 2)),
            'total_rooms' => $validated['total_rooms'] ?? 1,
        ]));

        $room->facilities()->sync($facilityIds);
        $this->inventorySync->syncHorizon((int) $hotel->id, (int) $room->room_type_id);
        $room->load(['roomType', 'roomUnits', 'facilities', 'images']);

        return response()->json(['success' => true, 'message' => 'Room created.', 'data' => $room], 201);
    }

    public function update(Request $request, int $hotelId, int $roomId): JsonResponse
    {
        $ownerId = $this->merchantOwnerId($request);
        $this->assertHotelAllowed($ownerId);

        $hotel = Hotel::query()->where('merchant_id', $ownerId)->findOrFail($hotelId);
        $room = HotelRoom::query()->where('hotel_id', $hotel->id)->findOrFail($roomId);

        $validated = $request->validate([
            'room_type_id' => ['sometimes', 'required', 'integer', 'exists:room_types,id'],
            'name' => ['sometimes', 'required', 'string', 'max:191'],
            'max_adults' => ['nullable', 'integer', 'min:0', 'max:20'],
            'max_children' => ['nullable', 'integer', 'min:0', 'max:20'],
            'max_occupancy' => ['nullable', 'integer', 'min:1', 'max:20'],
            'base_price' => ['sometimes', 'required', 'numeric', 'min:0'],
            'peak_price' => ['nullable', 'numeric', 'min:0'],
            'off_peak_price' => ['nullable', 'numeric', 'min:0'],
            'total_rooms' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'status' => ['nullable', 'integer', 'in:0,1,2'],
            'facility_ids' => ['nullable', 'array'],
            'facility_ids.*' => ['integer', 'exists:hotel_facilities,id'],
        ]);

        $facilityIds = array_key_exists('facility_ids', $validated) ? ($validated['facility_ids'] ?? []) : null;
        unset($validated['facility_ids']);

        if (array_key_exists('total_rooms', $validated)) {
            $unitCount = HotelRoomUnit::query()->where('hotel_room_id', $room->id)->count();
            $nextTotal = (int) $validated['total_rooms'];
            if ($nextTotal < $unitCount) {
                return response()->json([
                    'success' => false,
                    'message' => "Cannot set total rooms to {$nextTotal} while {$unitCount} room numbers exist. Remove extra units first.",
                ], 422);
            }
        }

        $room->fill($validated);
        $room->updated_by = (int) auth()->id();
        $room->save();

        if ($facilityIds !== null) {
            $room->facilities()->sync($facilityIds);
        }

        $this->inventorySync->syncHorizon((int) $hotel->id, (int) $room->room_type_id);
        $room->load(['roomType', 'roomUnits', 'facilities', 'images']);

        return response()->json(['success' => true, 'message' => 'Room updated.', 'data' => $room]);
    }
}
