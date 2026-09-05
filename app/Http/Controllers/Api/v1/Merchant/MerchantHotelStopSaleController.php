<?php

namespace App\Http\Controllers\Api\v1\Merchant;

use App\Models\Hotel;
use App\Models\HotelStopSale;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class MerchantHotelStopSaleController extends MerchantHotelBaseController
{
    public function index(Request $request, int $hotelId): JsonResponse
    {
        $hotel = $this->ownedHotel($request, $hotelId);
        if (! Schema::hasTable('hotel_stop_sales')) {
            return response()->json(['success' => true, 'data' => []]);
        }
        $items = HotelStopSale::query()
            ->where('hotel_id', $hotel->id)
            ->with(['roomType'])
            ->orderBy('starts_on')
            ->get();

        return response()->json(['success' => true, 'data' => $items]);
    }

    public function store(Request $request, int $hotelId): JsonResponse
    {
        $hotel = $this->ownedHotel($request, $hotelId);
        $validated = $this->validatedWindow($request, $hotel);

        $stop = HotelStopSale::create(array_merge($validated, [
            'hotel_id' => $hotel->id,
            'created_by' => $request->user()?->id,
        ]));
        $stop->load(['roomType']);

        return response()->json(['success' => true, 'message' => 'Stop-sale window created.', 'data' => $stop], 201);
    }

    public function update(Request $request, int $hotelId, int $id): JsonResponse
    {
        $hotel = $this->ownedHotel($request, $hotelId);
        $stop = HotelStopSale::query()->where('hotel_id', $hotel->id)->findOrFail($id);
        $validated = $this->validatedWindow($request, $hotel, false);

        $stop->fill($validated);
        $stop->save();
        $stop->load(['roomType']);

        return response()->json(['success' => true, 'message' => 'Stop-sale window updated.', 'data' => $stop]);
    }

    public function destroy(Request $request, int $hotelId, int $id): JsonResponse
    {
        $hotel = $this->ownedHotel($request, $hotelId);
        $stop = HotelStopSale::query()->where('hotel_id', $hotel->id)->findOrFail($id);
        $stop->delete();

        return response()->json(['success' => true, 'message' => 'Stop-sale window deleted.']);
    }

    private function ownedHotel(Request $request, int $hotelId): Hotel
    {
        $ownerId = $this->merchantOwnerId($request);
        $this->assertHotelAllowed($ownerId);

        return Hotel::query()->where('merchant_id', $ownerId)->findOrFail($hotelId);
    }

    private function validatedWindow(Request $request, Hotel $hotel, bool $required = true): array
    {
        $rule = $required ? 'required' : 'sometimes|required';
        $validated = $request->validate([
            'starts_on' => [$rule, 'date'],
            'ends_on' => [$rule, 'date', 'after_or_equal:starts_on'],
            'room_type_id' => ['nullable', 'integer'],
            'reason' => ['nullable', 'string', 'max:191'],
        ]);

        if (array_key_exists('room_type_id', $validated) && ($validated['room_type_id'] === '' || (int) $validated['room_type_id'] === 0)) {
            $validated['room_type_id'] = null;
        }

        $starts = $validated['starts_on'] ?? null;
        $ends = $validated['ends_on'] ?? null;
        if ($starts && $ends) {
            $days = Carbon::parse($starts)->startOfDay()->diffInDays(Carbon::parse($ends)->startOfDay()) + 1;
            if ($days > 366) {
                abort(response()->json([
                    'success' => false,
                    'message' => 'Stop-sale window cannot exceed 366 days.',
                    'errors' => ['ends_on' => ['Stop-sale window cannot exceed 366 days.']],
                ], 422));
            }
        }

        if (! empty($validated['room_type_id'])
            && ! $hotel->rooms()->where('room_type_id', (int) $validated['room_type_id'])->exists()) {
            abort(response()->json([
                'success' => false,
                'message' => 'Room type is not configured on this hotel.',
                'errors' => ['room_type_id' => ['Room type is not configured on this hotel.']],
            ], 422));
        }

        return $validated;
    }
}
