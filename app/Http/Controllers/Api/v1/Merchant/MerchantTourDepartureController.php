<?php

namespace App\Http\Controllers\Api\v1\Merchant;

use App\Models\Tour;
use App\Models\TourDeparture;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MerchantTourDepartureController extends MerchantTourBaseController
{
    public function index(Request $request, int $tourId): JsonResponse
    {
        $ownerId = $this->merchantOwnerId($request);
        $this->assertTourAllowed($ownerId);

        $tour = Tour::query()->where('merchant_id', $ownerId)->findOrFail($tourId);
        $items = TourDeparture::query()
            ->where('tour_id', $tour->id)
            ->orderBy('depart_date')
            ->get()
            ->map(function (TourDeparture $d) {
                $d->setAttribute('places_available', $d->availablePlaces());

                return $d;
            });

        return response()->json(['success' => true, 'data' => $items]);
    }

    public function store(Request $request, int $tourId): JsonResponse
    {
        $ownerId = $this->merchantOwnerId($request);
        $this->assertTourAllowed($ownerId);

        $tour = Tour::query()->where('merchant_id', $ownerId)->findOrFail($tourId);
        $validated = $request->validate([
            'depart_date' => [
                'required',
                'date',
                Rule::unique('tour_departures', 'depart_date')->where(fn ($q) => $q->where('tour_id', $tour->id)),
            ],
            'return_date' => ['nullable', 'date', 'after_or_equal:depart_date'],
            'places_total' => ['required', 'integer', 'min:1'],
            'unit_price' => ['required', 'numeric', 'min:0'],
            'status' => ['nullable', 'string', 'in:open,closed'],
        ]);

        $departure = TourDeparture::create([
            'tour_id' => $tour->id,
            'depart_date' => $validated['depart_date'],
            'return_date' => $validated['return_date'] ?? null,
            'places_total' => (int) $validated['places_total'],
            'places_sold' => 0,
            'places_held' => 0,
            'unit_price' => $validated['unit_price'],
            'status' => $validated['status'] ?? TourDeparture::STATUS_OPEN,
        ]);

        return response()->json(['success' => true, 'data' => $departure], 201);
    }

    public function update(Request $request, int $tourId, int $departureId): JsonResponse
    {
        $ownerId = $this->merchantOwnerId($request);
        $this->assertTourAllowed($ownerId);

        $tour = Tour::query()->where('merchant_id', $ownerId)->findOrFail($tourId);
        $departure = TourDeparture::query()->where('tour_id', $tour->id)->findOrFail($departureId);

        $validated = $request->validate([
            'depart_date' => [
                'sometimes',
                'date',
                Rule::unique('tour_departures', 'depart_date')
                    ->where(fn ($q) => $q->where('tour_id', $tour->id))
                    ->ignore($departure->id),
            ],
            'return_date' => ['nullable', 'date'],
            'places_total' => ['sometimes', 'integer', 'min:1'],
            'unit_price' => ['sometimes', 'numeric', 'min:0'],
            'status' => ['nullable', 'string', 'in:open,closed'],
        ]);

        if (isset($validated['places_total'])) {
            $minNeeded = (int) $departure->places_sold + (int) $departure->places_held;
            if ((int) $validated['places_total'] < $minNeeded) {
                return response()->json([
                    'success' => false,
                    'message' => 'places_total cannot be below sold+held.',
                ], 422);
            }
        }

        $departure->update($validated);

        return response()->json(['success' => true, 'data' => $departure->fresh()]);
    }

    public function destroy(Request $request, int $tourId, int $departureId): JsonResponse
    {
        $ownerId = $this->merchantOwnerId($request);
        $this->assertTourAllowed($ownerId);

        $tour = Tour::query()->where('merchant_id', $ownerId)->findOrFail($tourId);
        $departure = TourDeparture::query()->where('tour_id', $tour->id)->findOrFail($departureId);

        if ((int) $departure->places_sold > 0 || (int) $departure->places_held > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete departure with sold or held places.',
            ], 422);
        }

        $departure->delete();

        return response()->json(['success' => true, 'message' => 'Deleted.']);
    }
}
