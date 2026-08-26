<?php

namespace App\Http\Controllers\Api\v1\Merchant;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Hotel\Entities\Hotel;
use Modules\Hotel\Entities\HotelChildPolicy;

class MerchantHotelChildPolicyController extends MerchantHotelBaseController
{
    public function index(Request $request, int $hotelId): JsonResponse
    {
        $ownerId = $this->merchantOwnerId($request);
        $this->assertHotelAllowed($ownerId);

        $hotel = Hotel::query()->where('merchant_id', $ownerId)->findOrFail($hotelId);
        $items = HotelChildPolicy::query()
            ->where('hotel_id', $hotel->id)
            ->with(['ratePlan'])
            ->orderBy('min_age')
            ->get();

        return response()->json(['success' => true, 'data' => $items]);
    }

    public function store(Request $request, int $hotelId): JsonResponse
    {
        $ownerId = $this->merchantOwnerId($request);
        $this->assertHotelAllowed($ownerId);

        $hotel = Hotel::query()->where('merchant_id', $ownerId)->findOrFail($hotelId);

        $validated = $request->validate([
            'rate_plan_id' => ['nullable', 'integer'],
            'min_age' => ['required', 'integer', 'min:0', 'max:17'],
            'max_age' => ['required', 'integer', 'min:0', 'max:17'],
            'bed_type' => ['required', 'string', 'in:no_bed,extra_bed,adult_bed'],
            'price_type' => ['required', 'string', 'in:free,fixed,percentage,adult'],
            'price_value' => ['nullable', 'numeric', 'min:0'],
        ]);

        $policy = HotelChildPolicy::create(array_merge($validated, [
            'hotel_id' => $hotel->id,
        ]));
        $policy->load(['ratePlan']);

        return response()->json(['success' => true, 'message' => 'Child policy created.', 'data' => $policy], 201);
    }

    public function update(Request $request, int $hotelId, int $policyId): JsonResponse
    {
        $ownerId = $this->merchantOwnerId($request);
        $this->assertHotelAllowed($ownerId);

        $hotel = Hotel::query()->where('merchant_id', $ownerId)->findOrFail($hotelId);
        $policy = HotelChildPolicy::query()->where('hotel_id', $hotel->id)->findOrFail($policyId);

        $validated = $request->validate([
            'rate_plan_id' => ['nullable', 'integer'],
            'min_age' => ['sometimes', 'required', 'integer', 'min:0', 'max:17'],
            'max_age' => ['sometimes', 'required', 'integer', 'min:0', 'max:17'],
            'bed_type' => ['sometimes', 'required', 'string', 'in:no_bed,extra_bed,adult_bed'],
            'price_type' => ['sometimes', 'required', 'string', 'in:free,fixed,percentage,adult'],
            'price_value' => ['nullable', 'numeric', 'min:0'],
        ]);

        $policy->fill($validated);
        $policy->save();
        $policy->load(['ratePlan']);

        return response()->json(['success' => true, 'message' => 'Child policy updated.', 'data' => $policy]);
    }

    public function destroy(Request $request, int $hotelId, int $policyId): JsonResponse
    {
        $ownerId = $this->merchantOwnerId($request);
        $this->assertHotelAllowed($ownerId);

        $hotel = Hotel::query()->where('merchant_id', $ownerId)->findOrFail($hotelId);
        $policy = HotelChildPolicy::query()->where('hotel_id', $hotel->id)->findOrFail($policyId);
        $policy->delete();

        return response()->json(['success' => true, 'message' => 'Child policy deleted.']);
    }
}

