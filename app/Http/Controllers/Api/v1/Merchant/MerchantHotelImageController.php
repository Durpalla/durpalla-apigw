<?php

namespace App\Http\Controllers\Api\v1\Merchant;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Hotel\Entities\Hotel;
use Modules\Hotel\Entities\HotelImage;

class MerchantHotelImageController extends MerchantHotelBaseController
{
    public function index(Request $request, int $hotelId): JsonResponse
    {
        $ownerId = $this->merchantOwnerId($request);
        $this->assertHotelAllowed($ownerId);

        $hotel = Hotel::query()->where('merchant_id', $ownerId)->findOrFail($hotelId);
        $items = HotelImage::query()
            ->where('hotel_id', $hotel->id)
            ->orderBy('sort_order')
            ->get();

        return response()->json(['success' => true, 'data' => $items]);
    }

    public function store(Request $request, int $hotelId): JsonResponse
    {
        $ownerId = $this->merchantOwnerId($request);
        $this->assertHotelAllowed($ownerId);

        $hotel = Hotel::query()->where('merchant_id', $ownerId)->findOrFail($hotelId);

        $validated = $request->validate([
            'image_path' => ['nullable', 'string', 'max:512'],
            'image' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120'], // 5MB
            'type' => ['nullable', 'string', 'in:gallery,room,cover'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
        ]);

        $imagePath = $validated['image_path'] ?? null;
        if ($request->hasFile('image')) {
            $file = $request->file('image');
            $dir = 'hotels/'.$hotel->id;
            $disk = config('filesystems.uploads_disk', 'public');
            // Store relative disk path (not absolute URL) so image_url resolves with current APP_URL.
            $imagePath = $file->storePublicly($dir, ['disk' => $disk]);
        }
        if (! $imagePath) {
            return response()->json([
                'success' => false,
                'message' => 'image_path or image file is required.',
            ], 422);
        }

        $img = HotelImage::create([
            'hotel_id' => $hotel->id,
            'image_path' => $imagePath,
            'type' => $validated['type'] ?? 'gallery',
            'sort_order' => (int) ($validated['sort_order'] ?? 0),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Image added.',
            'data' => $img->fresh(),
        ], 201);
    }

    public function destroy(Request $request, int $hotelId, int $imageId): JsonResponse
    {
        $ownerId = $this->merchantOwnerId($request);
        $this->assertHotelAllowed($ownerId);

        $hotel = Hotel::query()->where('merchant_id', $ownerId)->findOrFail($hotelId);
        $img = HotelImage::query()->where('hotel_id', $hotel->id)->findOrFail($imageId);
        $img->delete();

        return response()->json(['success' => true, 'message' => 'Image deleted.']);
    }

    public function reorder(Request $request, int $hotelId): JsonResponse
    {
        $ownerId = $this->merchantOwnerId($request);
        $this->assertHotelAllowed($ownerId);

        $hotel = Hotel::query()->where('merchant_id', $ownerId)->findOrFail($hotelId);

        $validated = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.id' => ['required', 'integer'],
            'items.*.sort_order' => ['required', 'integer', 'min:0', 'max:65535'],
        ]);

        foreach ($validated['items'] as $row) {
            HotelImage::query()
                ->where('hotel_id', $hotel->id)
                ->where('id', (int) $row['id'])
                ->update(['sort_order' => (int) $row['sort_order']]);
        }

        $items = HotelImage::query()->where('hotel_id', $hotel->id)->orderBy('sort_order')->get();

        return response()->json(['success' => true, 'message' => 'Reordered.', 'data' => $items]);
    }
}

