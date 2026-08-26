<?php

namespace App\Http\Controllers\Api\v1\Merchant;

use App\Http\Controllers\Controller;
use App\Models\Vehicle;
use App\Models\VehicleImage;
use App\Support\ResolvesMerchantOwner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;

class MerchantVehicleImageController extends Controller
{
    use ResolvesMerchantOwner;

    public function index(Request $request, int $id): JsonResponse
    {
        $vehicle = $this->ownedVehicle($request, $id);
        $items = VehicleImage::query()
            ->where('vehicle_id', $vehicle->id)
            ->orderBy('sort_order')
            ->get()
            ->map(fn (VehicleImage $image) => $this->transformImage($image));

        return response()->json(['success' => true, 'data' => $items]);
    }

    public function store(Request $request, int $id): JsonResponse
    {
        $vehicle = $this->ownedVehicle($request, $id);

        $validated = $request->validate([
            'image' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'type' => ['nullable', 'string', 'in:gallery,thumb'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
        ]);

        $type = (string) ($validated['type'] ?? 'gallery');
        /** @var UploadedFile $file */
        $file = $validated['image'];
        $storedPath = $this->storeVehicleFile($file, $vehicle->id, $type);

        if ($type === 'thumb') {
            $this->replaceThumb($vehicle, $storedPath);
            $vehicle->refresh();

            return response()->json([
                'success' => true,
                'message' => 'Thumbnail updated.',
                'data' => [
                    'photo' => $vehicle->photo,
                    'photo_url' => $this->photoUrl($vehicle->photo),
                ],
            ]);
        }

        $image = VehicleImage::create([
            'vehicle_id' => $vehicle->id,
            'image_path' => $storedPath,
            'type' => 'gallery',
            'sort_order' => (int) ($validated['sort_order'] ?? VehicleImage::query()->where('vehicle_id', $vehicle->id)->count()),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Image added.',
            'data' => $this->transformImage($image),
        ], 201);
    }

    public function destroy(Request $request, int $id, int $imageId): JsonResponse
    {
        $vehicle = $this->ownedVehicle($request, $id);
        $image = VehicleImage::query()
            ->where('vehicle_id', $vehicle->id)
            ->findOrFail($imageId);

        $this->deleteStoredFile($image->image_path);
        $image->delete();

        return response()->json(['success' => true, 'message' => 'Image deleted.']);
    }

    public function uploadPhoto(Request $request, int $id): JsonResponse
    {
        $vehicle = $this->ownedVehicle($request, $id);

        $validated = $request->validate([
            'photo' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        /** @var UploadedFile $file */
        $file = $validated['photo'];
        $filename = $this->storeLegacyThumb($file);
        $this->replaceThumb($vehicle, $filename);
        $vehicle->refresh();

        return response()->json([
            'success' => true,
            'message' => 'Thumbnail updated.',
            'data' => [
                'photo' => $vehicle->photo,
                'photo_url' => $this->photoUrl($vehicle->photo),
            ],
        ]);
    }

    private function ownedVehicle(Request $request, int $id): Vehicle
    {
        $ownerId = $this->merchantOwnerId($request);

        return Vehicle::query()->where('merchant_id', $ownerId)->findOrFail($id);
    }

    private function replaceThumb(Vehicle $vehicle, string $storedPath): void
    {
        $previous = (string) ($vehicle->photo ?? '');
        if ($previous !== '' && $previous !== $storedPath) {
            $this->deleteStoredFile($previous);
        }
        $vehicle->photo = $storedPath;
        $vehicle->save();
    }

    private function storeLegacyThumb(UploadedFile $file): string
    {
        $directory = public_path('vehicles');
        if (! File::isDirectory($directory)) {
            File::makeDirectory($directory, 0755, true);
        }

        $filename = time().'_'.bin2hex(random_bytes(4)).'.'.$file->getClientOriginalExtension();
        $file->move($directory, $filename);

        return $filename;
    }

    private function storeVehicleFile(UploadedFile $file, int $vehicleId, string $type): string
    {
        if ($type === 'thumb') {
            return $this->storeLegacyThumb($file);
        }

        $relativeDir = 'vehicles/gallery/'.$vehicleId;
        $directory = public_path($relativeDir);
        if (! File::isDirectory($directory)) {
            File::makeDirectory($directory, 0755, true);
        }

        $filename = time().'_'.bin2hex(random_bytes(4)).'.'.$file->getClientOriginalExtension();
        $file->move($directory, $filename);

        return $relativeDir.'/'.$filename;
    }

    private function deleteStoredFile(?string $path): void
    {
        if ($path === null || $path === '') {
            return;
        }

        $candidates = [];
        if (str_contains($path, '/')) {
            $candidates[] = public_path(ltrim($path, '/'));
        } else {
            $candidates[] = public_path('vehicles/'.$path);
            $candidates[] = public_path($path);
        }

        foreach ($candidates as $candidate) {
            if (File::isFile($candidate)) {
                File::delete($candidate);
            }
        }
    }

    private function photoUrl(?string $photo): ?string
    {
        if ($photo === null || $photo === '') {
            return null;
        }
        if (str_starts_with($photo, 'http://') || str_starts_with($photo, 'https://')) {
            return $photo;
        }
        if (str_contains($photo, '/')) {
            return upload_asset(ltrim($photo, '/'));
        }

        return upload_asset('vehicles/'.$photo);
    }

    /**
     * @return array<string, mixed>
     */
    private function transformImage(VehicleImage $image): array
    {
        return [
            'id' => (int) $image->id,
            'vehicle_id' => (int) $image->vehicle_id,
            'image_path' => $image->image_path,
            'image_url' => $this->photoUrl($image->image_path),
            'type' => $image->type,
            'sort_order' => (int) $image->sort_order,
        ];
    }
}
