<?php

namespace App\Http\Controllers\Api\v1\Merchant;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Hotel\Entities\HotelFacility;
use Modules\Hotel\Entities\RoomType;
use Modules\Hotel\Entities\RoomRatePlan;

class MerchantHotelMetaController extends MerchantHotelBaseController
{
    public function facilities(Request $request): JsonResponse
    {
        $ownerId = $this->merchantOwnerId($request);
        $this->assertHotelAllowed($ownerId);

        $items = HotelFacility::query()
            ->orderBy('category')
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'icon', 'category']);

        return response()->json([
            'success' => true,
            'data' => $items,
        ]);
    }

    public function roomTypes(Request $request): JsonResponse
    {
        $ownerId = $this->merchantOwnerId($request);
        $this->assertHotelAllowed($ownerId);

        $categories = RoomType::categories();
        $items = RoomType::query()
            ->orderBy('category')
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'category', 'description'])
            ->map(function (RoomType $type) use ($categories) {
                $category = $type->category ?: RoomType::CATEGORY_ROOM;

                return [
                    'id' => $type->id,
                    'name' => $type->name,
                    'code' => $type->code,
                    'category' => $category,
                    'category_label' => $categories[$category] ?? ucfirst((string) $category),
                    'display_label' => $type->displayLabel(),
                    'description' => $type->description,
                ];
            })
            ->values();

        return response()->json([
            'success' => true,
            'data' => $items,
        ]);
    }

    public function ratePlans(Request $request): JsonResponse
    {
        $ownerId = $this->merchantOwnerId($request);
        $this->assertHotelAllowed($ownerId);

        $validated = $request->validate([
            'room_type_id' => ['nullable', 'integer'],
            'active_only' => ['nullable'],
        ]);

        $activeOnly = true;
        if ($request->has('active_only')) {
            $v = $validated['active_only'] ?? null;
            $activeOnly = filter_var($v, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
            $activeOnly = $activeOnly === null ? true : $activeOnly;
        }

        $roomTypeId = (int) ($validated['room_type_id'] ?? 0);

        $q = RoomRatePlan::query()->orderBy('name');
        if ($roomTypeId > 0) {
            $q->where('room_type_id', $roomTypeId);
        }
        if ($activeOnly) {
            $q->where('status', 1);
        }

        $items = $q->get(['id', 'room_type_id', 'name', 'meal_plan', 'status']);

        return response()->json([
            'success' => true,
            'data' => $items,
        ]);
    }
}
