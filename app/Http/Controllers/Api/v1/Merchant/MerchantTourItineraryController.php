<?php

namespace App\Http\Controllers\Api\v1\Merchant;

use App\Models\Tour;
use App\Models\TourItineraryDay;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MerchantTourItineraryController extends MerchantTourBaseController
{
    public function index(Request $request, int $tourId): JsonResponse
    {
        $ownerId = $this->merchantOwnerId($request);
        $this->assertTourAllowed($ownerId);

        $tour = Tour::query()->where('merchant_id', $ownerId)->findOrFail($tourId);
        $items = TourItineraryDay::query()
            ->where('tour_id', $tour->id)
            ->orderBy('day_number')
            ->get();

        return response()->json(['success' => true, 'data' => $items]);
    }

    public function store(Request $request, int $tourId): JsonResponse
    {
        $ownerId = $this->merchantOwnerId($request);
        $this->assertTourAllowed($ownerId);

        $tour = Tour::query()->where('merchant_id', $ownerId)->findOrFail($tourId);
        $validated = $request->validate([
            'day_number' => ['required', 'integer', 'min:1', 'max:365'],
            'title' => ['nullable', 'string', 'max:191'],
            'description' => ['nullable', 'string'],
        ]);

        $day = TourItineraryDay::updateOrCreate(
            ['tour_id' => $tour->id, 'day_number' => (int) $validated['day_number']],
            [
                'title' => $validated['title'] ?? null,
                'description' => $validated['description'] ?? null,
            ]
        );

        return response()->json(['success' => true, 'data' => $day], 201);
    }

    public function update(Request $request, int $tourId, int $dayId): JsonResponse
    {
        $ownerId = $this->merchantOwnerId($request);
        $this->assertTourAllowed($ownerId);

        $tour = Tour::query()->where('merchant_id', $ownerId)->findOrFail($tourId);
        $day = TourItineraryDay::query()->where('tour_id', $tour->id)->findOrFail($dayId);

        $validated = $request->validate([
            'day_number' => ['sometimes', 'integer', 'min:1', 'max:365'],
            'title' => ['nullable', 'string', 'max:191'],
            'description' => ['nullable', 'string'],
        ]);
        $day->update($validated);

        return response()->json(['success' => true, 'data' => $day]);
    }

    public function destroy(Request $request, int $tourId, int $dayId): JsonResponse
    {
        $ownerId = $this->merchantOwnerId($request);
        $this->assertTourAllowed($ownerId);

        $tour = Tour::query()->where('merchant_id', $ownerId)->findOrFail($tourId);
        TourItineraryDay::query()->where('tour_id', $tour->id)->where('id', $dayId)->delete();

        return response()->json(['success' => true, 'message' => 'Deleted.']);
    }
}
