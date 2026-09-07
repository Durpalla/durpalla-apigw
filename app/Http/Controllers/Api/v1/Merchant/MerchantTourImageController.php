<?php

namespace App\Http\Controllers\Api\v1\Merchant;

use App\Models\Tour;
use App\Models\TourImage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MerchantTourImageController extends MerchantTourBaseController
{
    public function index(Request $request, int $tourId): JsonResponse
    {
        $ownerId = $this->merchantOwnerId($request);
        $this->assertTourAllowed($ownerId);

        $tour = Tour::query()->where('merchant_id', $ownerId)->findOrFail($tourId);
        $items = TourImage::query()
            ->where('tour_id', $tour->id)
            ->orderBy('sort_order')
            ->get();

        return response()->json(['success' => true, 'data' => $items]);
    }

    public function store(Request $request, int $tourId): JsonResponse
    {
        $ownerId = $this->merchantOwnerId($request);
        $this->assertTourAllowed($ownerId);

        $tour = Tour::query()->where('merchant_id', $ownerId)->findOrFail($tourId);

        $validated = $request->validate([
            'image_path' => ['nullable', 'string', 'max:512'],
            'image' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'type' => ['nullable', 'string', 'in:gallery,cover'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
        ]);

        $imagePath = $validated['image_path'] ?? null;
        if ($request->hasFile('image')) {
            $disk = config('filesystems.uploads_disk', 'public');
            $imagePath = $request->file('image')->storePublicly('tours/'.$tour->id, ['disk' => $disk]);
        }
        if (! $imagePath) {
            return response()->json([
                'success' => false,
                'message' => 'Provide image or image_path.',
            ], 422);
        }

        $image = TourImage::create([
            'tour_id' => $tour->id,
            'image_path' => $imagePath,
            'type' => $validated['type'] ?? 'gallery',
            'sort_order' => (int) ($validated['sort_order'] ?? 0),
        ]);

        return response()->json(['success' => true, 'data' => $image], 201);
    }

    public function destroy(Request $request, int $tourId, int $imageId): JsonResponse
    {
        $ownerId = $this->merchantOwnerId($request);
        $this->assertTourAllowed($ownerId);

        $tour = Tour::query()->where('merchant_id', $ownerId)->findOrFail($tourId);
        $image = TourImage::query()->where('tour_id', $tour->id)->findOrFail($imageId);
        $image->delete();

        return response()->json(['success' => true, 'message' => 'Deleted.']);
    }

    public function reorder(Request $request, int $tourId): JsonResponse
    {
        $ownerId = $this->merchantOwnerId($request);
        $this->assertTourAllowed($ownerId);

        $tour = Tour::query()->where('merchant_id', $ownerId)->findOrFail($tourId);
        $validated = $request->validate([
            'order' => ['required', 'array', 'min:1'],
            'order.*' => ['integer'],
        ]);

        foreach (array_values($validated['order']) as $i => $imageId) {
            TourImage::query()
                ->where('tour_id', $tour->id)
                ->where('id', $imageId)
                ->update(['sort_order' => $i]);
        }

        return response()->json(['success' => true]);
    }
}
