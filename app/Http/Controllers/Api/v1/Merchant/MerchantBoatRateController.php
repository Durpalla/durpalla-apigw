<?php

namespace App\Http\Controllers\Api\v1\Merchant;

use App\Models\Boat;
use App\Models\BoatRate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MerchantBoatRateController extends MerchantBoatBaseController
{
    public function index(Request $request, int $boatId): JsonResponse
    {
        $ownerId = $this->merchantOwnerId($request);
        $this->assertBoatRentalAllowed($ownerId);

        $boat = Boat::query()->where('merchant_id', $ownerId)->findOrFail($boatId);
        $rates = BoatRate::query()->where('boat_id', $boat->id)->orderBy('rental_mode')->get();

        return response()->json(['success' => true, 'data' => $rates]);
    }

    public function upsert(Request $request, int $boatId): JsonResponse
    {
        $ownerId = $this->merchantOwnerId($request);
        $this->assertBoatRentalAllowed($ownerId);

        $boat = Boat::query()->where('merchant_id', $ownerId)->findOrFail($boatId);

        $validated = $request->validate([
            'rental_mode' => ['required', 'string', 'in:hourly,daily'],
            'unit_price' => ['required', 'numeric', 'min:0'],
            'min_units' => ['nullable', 'integer', 'min:1'],
            'max_units' => ['nullable', 'integer', 'min:1'],
        ]);

        $rate = BoatRate::query()->updateOrCreate(
            [
                'boat_id' => $boat->id,
                'rental_mode' => $validated['rental_mode'],
            ],
            [
                'unit_price' => round((float) $validated['unit_price'], 2),
                'min_units' => (int) ($validated['min_units'] ?? 1),
                'max_units' => isset($validated['max_units']) ? (int) $validated['max_units'] : null,
            ]
        );

        return response()->json(['success' => true, 'data' => $rate]);
    }

    public function destroy(Request $request, int $boatId, int $rateId): JsonResponse
    {
        $ownerId = $this->merchantOwnerId($request);
        $this->assertBoatRentalAllowed($ownerId);

        $boat = Boat::query()->where('merchant_id', $ownerId)->findOrFail($boatId);
        $rate = BoatRate::query()->where('boat_id', $boat->id)->findOrFail($rateId);
        $rate->delete();

        return response()->json(['success' => true, 'message' => 'Deleted.']);
    }
}
