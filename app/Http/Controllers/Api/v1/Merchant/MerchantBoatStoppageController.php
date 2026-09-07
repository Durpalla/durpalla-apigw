<?php

namespace App\Http\Controllers\Api\v1\Merchant;

use App\Models\BoatStoppage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MerchantBoatStoppageController extends MerchantBoatBaseController
{
    public function index(Request $request): JsonResponse
    {
        $ownerId = $this->merchantOwnerId($request);
        $this->assertBoatRentalAllowed($ownerId);

        $request->validate([
            'city_id' => ['nullable', 'integer'],
            'status' => ['nullable', 'integer'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);

        $q = BoatStoppage::query()
            ->with('city')
            ->where('merchant_id', $ownerId)
            ->orderBy('name');

        if ($request->filled('city_id')) {
            $q->where('city_id', (int) $request->city_id);
        }
        if ($request->filled('status')) {
            $q->where('status', (int) $request->status);
        }

        $paginator = $q->paginate((int) $request->get('per_page', 20));

        return response()->json([
            'success' => true,
            'data' => $paginator->items(),
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

        $data = $this->validatedStoppage($request);
        $data['merchant_id'] = $ownerId;
        $data['status'] = $data['status'] ?? 1;

        $stoppage = BoatStoppage::create($data);

        return response()->json(['success' => true, 'data' => $stoppage], 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $ownerId = $this->merchantOwnerId($request);
        $this->assertBoatRentalAllowed($ownerId);

        $stoppage = BoatStoppage::query()
            ->with('city')
            ->where('merchant_id', $ownerId)
            ->findOrFail($id);

        return response()->json(['success' => true, 'data' => $stoppage]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $ownerId = $this->merchantOwnerId($request);
        $this->assertBoatRentalAllowed($ownerId);

        $stoppage = BoatStoppage::query()->where('merchant_id', $ownerId)->findOrFail($id);
        $stoppage->update($this->validatedStoppage($request, false));

        return response()->json(['success' => true, 'data' => $stoppage->fresh()]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $ownerId = $this->merchantOwnerId($request);
        $this->assertBoatRentalAllowed($ownerId);

        $stoppage = BoatStoppage::query()->where('merchant_id', $ownerId)->findOrFail($id);
        $stoppage->delete();

        return response()->json(['success' => true, 'message' => 'Deleted.']);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedStoppage(Request $request, bool $creating = true): array
    {
        return $request->validate([
            'name' => [$creating ? 'required' : 'sometimes', 'string', 'max:191'],
            'description' => ['nullable', 'string'],
            'city_id' => ['nullable', 'integer'],
            'latitude' => ['nullable', 'numeric'],
            'longitude' => ['nullable', 'numeric'],
            'status' => ['nullable', 'integer', 'in:0,1'],
        ]);
    }
}
