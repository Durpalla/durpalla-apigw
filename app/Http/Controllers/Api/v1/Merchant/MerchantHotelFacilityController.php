<?php

namespace App\Http\Controllers\Api\v1\Merchant;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Hotel\Entities\Hotel;

class MerchantHotelFacilityController extends MerchantHotelBaseController
{
    public function sync(Request $request, int $hotelId): JsonResponse
    {
        $ownerId = $this->merchantOwnerId($request);
        $this->assertHotelAllowed($ownerId);

        $validated = $request->validate([
            'facility_ids' => ['required', 'array'],
            'facility_ids.*' => ['integer'],
        ]);

        $hotel = Hotel::query()->where('merchant_id', $ownerId)->findOrFail($hotelId);
        $hotel->facilities()->sync($validated['facility_ids']);
        $hotel->load(['facilities']);

        return response()->json([
            'success' => true,
            'message' => 'Facilities updated.',
            'data' => $hotel->facilities,
        ]);
    }
}

