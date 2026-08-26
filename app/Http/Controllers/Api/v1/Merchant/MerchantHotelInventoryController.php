<?php

namespace App\Http\Controllers\Api\v1\Merchant;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Hotel;
use App\Models\HotelRoomInventory;

class MerchantHotelInventoryController extends MerchantHotelBaseController
{
    /**
     * PUT /api/v1/merchant/hotels/{hotelId}/inventory
     *
     * Body:
     * - room_type_id: int
     * - items: [{ date: YYYY-MM-DD, total_rooms: int }]
     */
    public function upsert(Request $request, int $hotelId): JsonResponse
    {
        $ownerId = $this->merchantOwnerId($request);
        $this->assertHotelAllowed($ownerId);

        $hotel = Hotel::query()->where('merchant_id', $ownerId)->findOrFail($hotelId);

        $validated = $request->validate([
            'room_type_id' => ['required', 'integer'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.date' => ['required', 'date_format:Y-m-d'],
            'items.*.total_rooms' => ['required', 'integer', 'min:0', 'max:100000'],
        ]);

        $roomTypeId = (int) $validated['room_type_id'];

        DB::transaction(function () use ($hotel, $roomTypeId, $validated) {
            foreach ($validated['items'] as $row) {
                $date = (string) $row['date'];
                $totalRooms = (int) $row['total_rooms'];

                $inv = HotelRoomInventory::query()
                    ->where('hotel_id', $hotel->id)
                    ->where('room_type_id', $roomTypeId)
                    ->where('date', $date)
                    ->lockForUpdate()
                    ->first();

                if (! $inv) {
                    HotelRoomInventory::create([
                        'hotel_id' => $hotel->id,
                        'room_type_id' => $roomTypeId,
                        'date' => $date,
                        'total_rooms' => $totalRooms,
                        'booked_rooms' => 0,
                        'held_rooms' => 0,
                        'available_rooms' => $totalRooms,
                    ]);
                    continue;
                }

                $booked = (int) ($inv->booked_rooms ?? 0);
                $held = (int) ($inv->held_rooms ?? 0);
                $available = max(0, $totalRooms - $booked - $held);

                $inv->total_rooms = $totalRooms;
                $inv->available_rooms = $available;
                $inv->save();
            }
        }, 3);

        $rows = HotelRoomInventory::query()
            ->where('hotel_id', $hotel->id)
            ->where('room_type_id', $roomTypeId)
            ->orderBy('date')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Inventory updated.',
            'data' => $rows,
        ]);
    }
}

