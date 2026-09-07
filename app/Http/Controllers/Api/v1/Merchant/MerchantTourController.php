<?php

namespace App\Http\Controllers\Api\v1\Merchant;

use App\Models\Tour;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MerchantTourController extends MerchantTourBaseController
{
    public function index(Request $request): JsonResponse
    {
        $ownerId = $this->merchantOwnerId($request);
        $this->assertTourAllowed($ownerId);

        $request->validate([
            'search' => ['nullable', 'string', 'max:191'],
            'city_id' => ['nullable', 'integer'],
            'status' => ['nullable', 'integer'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);

        $q = Tour::query()
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
                $inner->where('title', 'LIKE', $s)->orWhere('destination', 'LIKE', $s);
            });
        }
        if ($request->filled('city_id')) {
            $q->where('city_id', (int) $request->city_id);
        }
        if ($request->filled('status')) {
            $q->where('status', (int) $request->status);
        }

        $paginator = $q->paginate((int) $request->get('per_page', 10));
        $items = collect($paginator->items())->map(function (Tour $tour) {
            $cover = $tour->images->first();
            $tour->setAttribute('thumbnail_url', $cover?->image_url);
            $tour->setAttribute('city_name', $tour->city?->name);
            $tour->unsetRelation('images');

            return $tour;
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
        $this->assertTourAllowed($ownerId);

        $data = $this->validatedTour($request);
        $data['merchant_id'] = $ownerId;
        $data['status'] = $data['status'] ?? 1;

        $tour = Tour::create($data);

        return response()->json(['success' => true, 'data' => $tour], 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $ownerId = $this->merchantOwnerId($request);
        $this->assertTourAllowed($ownerId);

        $tour = Tour::query()
            ->with(['city', 'images', 'itineraryDays', 'departures'])
            ->where('merchant_id', $ownerId)
            ->findOrFail($id);

        return response()->json(['success' => true, 'data' => $tour]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $ownerId = $this->merchantOwnerId($request);
        $this->assertTourAllowed($ownerId);

        $tour = Tour::query()->where('merchant_id', $ownerId)->findOrFail($id);
        $tour->update($this->validatedTour($request, false));

        return response()->json(['success' => true, 'data' => $tour->fresh()]);
    }

    public function updateStatus(Request $request, int $id): JsonResponse
    {
        $ownerId = $this->merchantOwnerId($request);
        $this->assertTourAllowed($ownerId);

        $validated = $request->validate([
            'status' => ['required', 'integer', 'in:0,1'],
        ]);

        $tour = Tour::query()->where('merchant_id', $ownerId)->findOrFail($id);
        $tour->update(['status' => (int) $validated['status']]);

        return response()->json(['success' => true, 'data' => $tour]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $ownerId = $this->merchantOwnerId($request);
        $this->assertTourAllowed($ownerId);

        $tour = Tour::query()->where('merchant_id', $ownerId)->findOrFail($id);
        $tour->delete();

        return response()->json(['success' => true, 'message' => 'Deleted.']);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedTour(Request $request, bool $creating = true): array
    {
        $rules = [
            'title' => [$creating ? 'required' : 'sometimes', 'string', 'max:191'],
            'destination' => ['nullable', 'string', 'max:191'],
            'city_id' => ['nullable', 'integer'],
            'duration_days' => ['nullable', 'integer', 'min:1', 'max:365'],
            'duration_nights' => ['nullable', 'integer', 'min:0', 'max:365'],
            'status' => ['nullable', 'integer', 'in:0,1'],
            'short_description' => ['nullable', 'string'],
            'long_description' => ['nullable', 'string'],
            'meeting_point' => ['nullable', 'string', 'max:255'],
            'min_people' => ['nullable', 'integer', 'min:1'],
            'max_people' => ['nullable', 'integer', 'min:1'],
            'cancellation_policy' => ['nullable', 'string'],
        ];

        return $request->validate($rules);
    }
}
