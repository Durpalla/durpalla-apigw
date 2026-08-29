<?php

namespace App\Http\Controllers\Api\v1\Merchant;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Models\Hotel;

class MerchantHotelController extends MerchantHotelBaseController
{
    public function index(Request $request): JsonResponse
    {
        $ownerId = $this->merchantOwnerId($request);
        $this->assertHotelAllowed($ownerId);

        $request->validate([
            'search' => ['nullable', 'string', 'max:191'],
            'city_id' => ['nullable', 'integer'],
            'country_id' => ['nullable', 'integer'],
            'country' => ['nullable', 'string', 'max:191'],
            'status' => ['nullable', 'integer'],
            'source' => ['nullable', 'string', 'max:32'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);

        $q = Hotel::query()
            ->with([
                'city.countryRelation',
                'images' => function ($iq) {
                    $iq->orderByRaw("CASE WHEN type = 'cover' THEN 0 ELSE 1 END")
                        ->orderBy('sort_order')
                        ->orderBy('id');
                },
            ])
            ->withSum('rooms as total_rooms_sum', 'total_rooms')
            ->where('merchant_id', $ownerId)
            ->orderByDesc('id');

        if ($request->filled('search')) {
            $s = '%'.trim((string) $request->search).'%';
            $q->where('name', 'LIKE', $s);
        }
        if ($request->filled('city_id')) {
            $q->where('city_id', (int) $request->city_id);
        }
        if ($request->filled('country_id')) {
            $countryId = (int) $request->country_id;
            $q->whereHas('city', function ($cq) use ($countryId) {
                $cq->where('country_id', $countryId);
            });
        }
        if ($request->filled('country')) {
            $country = trim((string) $request->country);
            $q->whereHas('city', function ($cq) use ($country) {
                $cq->where(function ($inner) use ($country) {
                    $inner->where('country', $country)
                        ->orWhereHas('countryRelation', function ($rq) use ($country) {
                            $rq->where('name', $country);
                        });
                });
            });
        }
        if ($request->filled('status')) {
            $q->where('status', (int) $request->status);
        }
        if ($request->filled('source')) {
            $q->where('source', (string) $request->source);
        }

        $perPage = (int) ($request->get('per_page', 10));
        $paginator = $q->paginate($perPage);

        $items = collect($paginator->items())->map(function (Hotel $hotel) {
            $cover = $hotel->images->first();
            $hotel->setAttribute('thumbnail_url', $cover?->image_url);
            $hotel->setAttribute('city_name', $hotel->city?->name);
            $hotel->setAttribute('country_name', $hotel->city?->country_name);
            $hotel->setAttribute('total_rooms', (int) ($hotel->total_rooms_sum ?? 0));
            // Keep payload lean for list views.
            $hotel->unsetRelation('images');

            return $hotel;
        })->values();

        return response()->json([
            'success' => true,
            'data' => $items,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $ownerId = $this->merchantOwnerId($request);
        $this->assertHotelAllowed($ownerId);
        $this->saasEntitlements()->assertCanCreateHotelProperty($ownerId);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:191'],
            'city_id' => ['required', 'integer'],
            'address' => ['nullable', 'string', 'max:191'],
            'rating' => ['nullable', 'numeric', 'min:0', 'max:5'],
            'check_in_time' => ['nullable', 'date_format:H:i'],
            'check_out_time' => ['nullable', 'date_format:H:i'],
            'status' => ['nullable', 'integer', 'in:0,1,2'],
            'source' => ['nullable', 'string', 'max:32'],
            'external_id' => ['nullable', 'string', 'max:191'],
            'supplier_meta' => ['nullable', 'array'],
        ]);

        $payload = array_merge($validated, [
            'merchant_id' => $ownerId,
            'created_by' => (int) auth()->id(),
            'updated_by' => (int) auth()->id(),
            'source' => $validated['source'] ?? 'local',
            'status' => $validated['status'] ?? 1,
        ]);

        $hotel = Hotel::create($payload);
        $hotel->load(['city']);

        return response()->json([
            'success' => true,
            'message' => 'Hotel created.',
            'data' => $hotel,
        ], 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $ownerId = $this->merchantOwnerId($request);
        $this->assertHotelAllowed($ownerId);

        $with = [
            'city',
            'rooms.roomType',
            'rooms.roomUnits',
            'facilities',
            'images',
            'childPolicies.ratePlan',
        ];
        foreach ([
            'descriptions' => 'hotel_descriptions',
            'policies' => 'hotel_policies',
            'contact' => 'hotel_contacts',
            'location' => 'hotel_locations',
        ] as $relation => $table) {
            if (\Illuminate\Support\Facades\Schema::hasTable($table)) {
                $with[] = $relation;
            }
        }

        $hotel = Hotel::query()
            ->where('merchant_id', $ownerId)
            ->with($with)
            ->findOrFail($id);

        return response()->json(['success' => true, 'data' => $hotel]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $ownerId = $this->merchantOwnerId($request);
        $this->assertHotelAllowed($ownerId);

        $hotel = Hotel::query()->where('merchant_id', $ownerId)->findOrFail($id);

        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:191'],
            'city_id' => ['sometimes', 'required', 'integer'],
            'address' => ['nullable', 'string', 'max:191'],
            'rating' => ['nullable', 'numeric', 'min:0', 'max:5'],
            'check_in_time' => ['nullable', 'date_format:H:i'],
            'check_out_time' => ['nullable', 'date_format:H:i'],
            'status' => ['nullable', 'integer', 'in:0,1,2'],
        ]);

        $hotel->fill($validated);
        $hotel->updated_by = (int) auth()->id();
        $hotel->save();

        $hotel->load(['city']);

        return response()->json([
            'success' => true,
            'message' => 'Hotel updated.',
            'data' => $hotel,
        ]);
    }

    public function updateStatus(Request $request, int $id): JsonResponse
    {
        $ownerId = $this->merchantOwnerId($request);
        $this->assertHotelAllowed($ownerId);

        $hotel = Hotel::query()->where('merchant_id', $ownerId)->findOrFail($id);

        $validated = $request->validate([
            'status' => ['required', 'integer', 'in:0,1,2'],
        ]);

        $hotel->status = (int) $validated['status'];
        $hotel->updated_by = (int) auth()->id();
        $hotel->save();

        return response()->json([
            'success' => true,
            'message' => 'Status updated.',
            'data' => $hotel,
        ]);
    }
}

