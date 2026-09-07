<?php

namespace App\Http\Controllers\Api\v1\Merchant;

use App\Models\Boat;
use App\Models\BoatImage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MerchantBoatImageController extends MerchantBoatBaseController
{
    public function index(Request $request, int $boatId): JsonResponse
    {
        $ownerId = $this->merchantOwnerId($request);
        $this->assertBoatRentalAllowed($ownerId);

        $boat = Boat::query()->where('merchant_id', $ownerId)->findOrFail($boatId);
        $items = BoatImage::query()
            ->where('boat_id', $boat->id)
            ->orderBy('sort_order')
            ->get();

        return response()->json(['success' => true, 'data' => $items]);
    }

    public function store(Request $request, int $boatId): JsonResponse
    {
        $ownerId = $this->merchantOwnerId($request);
        $this->assertBoatRentalAllowed($ownerId);

        $boat = Boat::query()->where('merchant_id', $ownerId)->findOrFail($boatId);

        $validated = $request->validate([
            'image_path' => ['nullable', 'string', 'max:512'],
            'image' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'type' => ['nullable', 'string', 'in:gallery,cover'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
        ]);

        $imagePath = $validated['image_path'] ?? null;
        if ($request->hasFile('image')) {
            $disk = config('filesystems.uploads_disk', 'public');
            $imagePath = $request->file('image')->storePublicly('boats/'.$boat->id, ['disk' => $disk]);
        }
        if (! $imagePath) {
            return response()->json([
                'success' => false,
                'message' => 'Provide image or image_path.',
            ], 422);
        }

        $image = BoatImage::create([
            'boat_id' => $boat->id,
            'image_path' => $imagePath,
            'type' => $validated['type'] ?? 'gallery',
            'sort_order' => (int) ($validated['sort_order'] ?? 0),
        ]);

        return response()->json(['success' => true, 'data' => $image], 201);
    }

    public function destroy(Request $request, int $boatId, int $imageId): JsonResponse
    {
        $ownerId = $this->merchantOwnerId($request);
        $this->assertBoatRentalAllowed($ownerId);

        $boat = Boat::query()->where('merchant_id', $ownerId)->findOrFail($boatId);
        $image = BoatImage::query()->where('boat_id', $boat->id)->findOrFail($imageId);
        $image->delete();

        return response()->json(['success' => true, 'message' => 'Deleted.']);
    }

    public function reorder(Request $request, int $boatId): JsonResponse
    {
        $ownerId = $this->merchantOwnerId($request);
        $this->assertBoatRentalAllowed($ownerId);

        $boat = Boat::query()->where('merchant_id', $ownerId)->findOrFail($boatId);
        $validated = $request->validate([
            'order' => ['required', 'array', 'min:1'],
            'order.*' => ['integer'],
        ]);

        foreach (array_values($validated['order']) as $i => $imageId) {
            BoatImage::query()
                ->where('boat_id', $boat->id)
                ->where('id', $imageId)
                ->update(['sort_order' => $i]);
        }

        return response()->json(['success' => true]);
    }
}
