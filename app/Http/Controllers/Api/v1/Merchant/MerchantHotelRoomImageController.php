<?php

namespace App\Http\Controllers\Api\v1\Merchant;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Models\Hotel;
use App\Models\HotelRoom;
use App\Models\HotelRoomImage;

class MerchantHotelRoomImageController extends MerchantHotelBaseController
{
    public function index(Request $request, int $hotelId, int $roomId): JsonResponse
    {
        $room = $this->resolveRoom($request, $hotelId, $roomId);
        $items = HotelRoomImage::query()
            ->where('hotel_room_id', $room->id)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return response()->json(['success' => true, 'data' => $items]);
    }

    public function store(Request $request, int $hotelId, int $roomId): JsonResponse
    {
        $room = $this->resolveRoom($request, $hotelId, $roomId);

        $validated = $request->validate([
            'image_path' => ['nullable', 'string', 'max:512'],
            'image' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'type' => ['nullable', 'string', 'in:gallery,cover'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
        ]);

        $imagePath = $validated['image_path'] ?? null;
        if ($request->hasFile('image')) {
            $disk = config('filesystems.uploads_disk', 'public');
            $dir = 'hotels/'.$hotelId.'/rooms/'.$room->id;
            $imagePath = $request->file('image')->storePublicly($dir, ['disk' => $disk]);
        }
        if (! $imagePath) {
            return response()->json([
                'success' => false,
                'message' => 'image_path or image file is required.',
            ], 422);
        }

        $img = HotelRoomImage::create([
            'hotel_room_id' => $room->id,
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

    public function destroy(Request $request, int $hotelId, int $roomId, int $imageId): JsonResponse
    {
        $room = $this->resolveRoom($request, $hotelId, $roomId);
        $img = HotelRoomImage::query()
            ->where('hotel_room_id', $room->id)
            ->findOrFail($imageId);

        $path = (string) $img->image_path;
        $img->delete();

        if ($path !== '' && ! preg_match('#^https?://#i', $path)) {
            $disk = config('filesystems.uploads_disk', 'public');
            try {
                Storage::disk($disk)->delete(ltrim($path, '/'));
            } catch (\Throwable) {
                // Ignore missing files / disk errors.
            }
        }

        return response()->json(['success' => true, 'message' => 'Image deleted.']);
    }

    private function resolveRoom(Request $request, int $hotelId, int $roomId): HotelRoom
    {
        $ownerId = $this->merchantOwnerId($request);
        $this->assertHotelAllowed($ownerId);

        $hotel = Hotel::query()->where('merchant_id', $ownerId)->findOrFail($hotelId);

        return HotelRoom::query()
            ->where('hotel_id', $hotel->id)
            ->findOrFail($roomId);
    }
}
