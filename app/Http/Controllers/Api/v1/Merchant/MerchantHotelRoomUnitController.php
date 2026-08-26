<?php

namespace App\Http\Controllers\Api\v1\Merchant;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use App\Models\Hotel;
use App\Models\HotelRoom;
use App\Models\HotelRoomUnit;

class MerchantHotelRoomUnitController extends MerchantHotelBaseController
{
    public function index(Request $request, int $hotelId, int $roomId): JsonResponse
    {
        $ownerId = $this->merchantOwnerId($request);
        $this->assertHotelAllowed($ownerId);

        $hotel = Hotel::query()->where('merchant_id', $ownerId)->findOrFail($hotelId);
        $room = HotelRoom::query()->where('hotel_id', $hotel->id)->findOrFail($roomId);

        $units = HotelRoomUnit::query()
            ->where('hotel_room_id', $room->id)
            ->orderBy('room_number')
            ->get();

        return response()->json(['success' => true, 'data' => $units]);
    }

    public function store(Request $request, int $hotelId, int $roomId): JsonResponse
    {
        $ownerId = $this->merchantOwnerId($request);
        $this->assertHotelAllowed($ownerId);

        $hotel = Hotel::query()->where('merchant_id', $ownerId)->findOrFail($hotelId);
        $room = HotelRoom::query()->where('hotel_id', $hotel->id)->findOrFail($roomId);

        $validated = $request->validate([
            'room_number' => [
                'required',
                'string',
                'max:64',
                Rule::unique('hotel_room_units', 'room_number')->where('hotel_room_id', $room->id),
            ],
            'floor' => ['nullable', 'string', 'max:64'],
            'status' => ['nullable', 'integer', 'in:0,1,2'],
        ], [
            'room_number.unique' => 'Room number :input already exists for this room inventory.',
        ]);

        $limit = (int) ($room->total_rooms ?? 0);
        $currentCount = HotelRoomUnit::query()->where('hotel_room_id', $room->id)->count();
        if ($limit > 0 && $currentCount >= $limit) {
            return response()->json([
                'success' => false,
                'message' => "This room inventory allows {$limit} room numbers. Remove one or increase Total rooms.",
            ], 422);
        }

        $unit = HotelRoomUnit::create([
            'hotel_room_id' => $room->id,
            'room_number' => $validated['room_number'],
            'floor' => $validated['floor'] ?? null,
            'status' => $validated['status'] ?? 1,
        ]);

        return response()->json(['success' => true, 'message' => 'Unit created.', 'data' => $unit], 201);
    }
}
