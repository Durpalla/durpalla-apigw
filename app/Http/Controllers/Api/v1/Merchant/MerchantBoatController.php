<?php

namespace App\Http\Controllers\Api\v1\Merchant;

use App\Models\Boat;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MerchantBoatController extends MerchantBoatBaseController
{
    public function index(Request $request): JsonResponse
    {
        $ownerId = $this->merchantOwnerId($request);
        $this->assertBoatRentalAllowed($ownerId);

        $request->validate([
            'search' => ['nullable', 'string', 'max:191'],
            'city_id' => ['nullable', 'integer'],
            'status' => ['nullable', 'integer'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);

        $q = Boat::query()
            ->with([
                'city',
                'images' => fn ($iq) => $iq->orderByRaw("CASE WHEN type = 'cover' THEN 0 ELSE 1 END")
                    ->orderBy('sort_order')
                    ->orderBy('id'),
            ])
            ->where('merchant_id', $ownerId)
            ->orderByDesc('id');

        if ($request->filled('search')) {
            $s = '%'.trim((string) $request->search).'%';
            $q->where(function ($inner) use ($s) {
                $inner->where('title', 'LIKE', $s)->orWhere('base_location', 'LIKE', $s);
            });
        }
        if ($request->filled('city_id')) {
            $q->where('city_id', (int) $request->city_id);
        }
        if ($request->filled('status')) {
            $q->where('status', (int) $request->status);
        }

        $paginator = $q->paginate((int) $request->get('per_page', 10));
        $items = collect($paginator->items())->map(function (Boat $boat) {
            $cover = $boat->images->first();
            $boat->setAttribute('thumbnail_url', $cover?->image_url);
            $boat->setAttribute('city_name', $boat->city?->name);
            $boat->unsetRelation('images');

            return $boat;
        })->values();

        return response()->json([
            'success' => true,
            'data' => $items,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $ownerId = $this->merchantOwnerId($request);
        $this->assertBoatRentalAllowed($ownerId);

        $data = $this->validatedBoat($request);
        $data['merchant_id'] = $ownerId;
        $data['status'] = $data['status'] ?? 1;

        $boat = Boat::create($data);

        return response()->json(['success' => true, 'data' => $boat], 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $ownerId = $this->merchantOwnerId($request);
        $this->assertBoatRentalAllowed($ownerId);

        $boat = Boat::query()
            ->with(['city', 'images', 'rates', 'tripPackages.stops'])
            ->where('merchant_id', $ownerId)
            ->findOrFail($id);

        return response()->json(['success' => true, 'data' => $boat]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $ownerId = $this->merchantOwnerId($request);
        $this->assertBoatRentalAllowed($ownerId);

        $boat = Boat::query()->where('merchant_id', $ownerId)->findOrFail($id);
        $boat->update($this->validatedBoat($request, false));

        return response()->json(['success' => true, 'data' => $boat->fresh()]);
    }

    public function updateStatus(Request $request, int $id): JsonResponse
    {
        $ownerId = $this->merchantOwnerId($request);
        $this->assertBoatRentalAllowed($ownerId);

        $validated = $request->validate([
            'status' => ['required', 'integer', 'in:0,1'],
        ]);

        $boat = Boat::query()->where('merchant_id', $ownerId)->findOrFail($id);
        $boat->update(['status' => (int) $validated['status']]);

        return response()->json(['success' => true, 'data' => $boat]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $ownerId = $this->merchantOwnerId($request);
        $this->assertBoatRentalAllowed($ownerId);

        $boat = Boat::query()->where('merchant_id', $ownerId)->findOrFail($id);
        $boat->delete();

        return response()->json(['success' => true, 'message' => 'Deleted.']);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedBoat(Request $request, bool $creating = true): array
    {
        return $request->validate([
            'title' => [$creating ? 'required' : 'sometimes', 'string', 'max:191'],
            'short_description' => ['nullable', 'string'],
            'long_description' => ['nullable', 'string'],
            'capacity_max' => [$creating ? 'required' : 'sometimes', 'integer', 'min:1', 'max:500'],
            'city_id' => ['nullable', 'integer'],
            'base_location' => ['nullable', 'string', 'max:255'],
            'amenities' => ['nullable', 'array'],
            'status' => ['nullable', 'integer', 'in:0,1'],
            'cancellation_policy' => ['nullable', 'string'],
        ]);
    }
}
