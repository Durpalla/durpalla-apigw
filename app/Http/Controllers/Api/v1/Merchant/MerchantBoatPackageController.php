<?php

namespace App\Http\Controllers\Api\v1\Merchant;

use App\Models\Boat;
use App\Models\BoatTripPackage;
use App\Models\BoatTripPackageStop;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MerchantBoatPackageController extends MerchantBoatBaseController
{
    public function index(Request $request, int $boatId): JsonResponse
    {
        $ownerId = $this->merchantOwnerId($request);
        $this->assertBoatRentalAllowed($ownerId);

        $boat = Boat::query()->where('merchant_id', $ownerId)->findOrFail($boatId);
        $packages = BoatTripPackage::query()
            ->where('boat_id', $boat->id)
            ->with('stops.stoppage')
            ->orderByDesc('id')
            ->get();

        return response()->json(['success' => true, 'data' => $packages]);
    }

    public function store(Request $request, int $boatId): JsonResponse
    {
        $ownerId = $this->merchantOwnerId($request);
        $this->assertBoatRentalAllowed($ownerId);

        $boat = Boat::query()->where('merchant_id', $ownerId)->findOrFail($boatId);
        $data = $this->validatedPackage($request);
        $stoppageIds = $data['stoppage_ids'] ?? [];
        unset($data['stoppage_ids']);
        $data['boat_id'] = $boat->id;
        $data['status'] = $data['status'] ?? 1;

        $package = DB::transaction(function () use ($data, $stoppageIds) {
            $package = BoatTripPackage::create($data);
            $this->syncStops($package, $stoppageIds);

            return $package->load('stops.stoppage');
        });

        return response()->json(['success' => true, 'data' => $package], 201);
    }

    public function show(Request $request, int $boatId, int $packageId): JsonResponse
    {
        $ownerId = $this->merchantOwnerId($request);
        $this->assertBoatRentalAllowed($ownerId);

        $boat = Boat::query()->where('merchant_id', $ownerId)->findOrFail($boatId);
        $package = BoatTripPackage::query()
            ->where('boat_id', $boat->id)
            ->with('stops.stoppage')
            ->findOrFail($packageId);

        return response()->json(['success' => true, 'data' => $package]);
    }

    public function update(Request $request, int $boatId, int $packageId): JsonResponse
    {
        $ownerId = $this->merchantOwnerId($request);
        $this->assertBoatRentalAllowed($ownerId);

        $boat = Boat::query()->where('merchant_id', $ownerId)->findOrFail($boatId);
        $package = BoatTripPackage::query()->where('boat_id', $boat->id)->findOrFail($packageId);

        $data = $this->validatedPackage($request, false);
        $stoppageIds = array_key_exists('stoppage_ids', $data) ? $data['stoppage_ids'] : null;
        unset($data['stoppage_ids']);

        $package = DB::transaction(function () use ($package, $data, $stoppageIds) {
            if ($data !== []) {
                $package->update($data);
            }
            if (is_array($stoppageIds)) {
                $this->syncStops($package, $stoppageIds);
            }

            return $package->fresh('stops.stoppage');
        });

        return response()->json(['success' => true, 'data' => $package]);
    }

    public function destroy(Request $request, int $boatId, int $packageId): JsonResponse
    {
        $ownerId = $this->merchantOwnerId($request);
        $this->assertBoatRentalAllowed($ownerId);

        $boat = Boat::query()->where('merchant_id', $ownerId)->findOrFail($boatId);
        $package = BoatTripPackage::query()->where('boat_id', $boat->id)->findOrFail($packageId);
        $package->delete();

        return response()->json(['success' => true, 'message' => 'Deleted.']);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedPackage(Request $request, bool $creating = true): array
    {
        return $request->validate([
            'title' => [$creating ? 'required' : 'sometimes', 'string', 'max:191'],
            'duration_hours' => [$creating ? 'required' : 'sometimes', 'integer', 'min:1', 'max:720'],
            'base_price' => [$creating ? 'required' : 'sometimes', 'numeric', 'min:0'],
            'status' => ['nullable', 'integer', 'in:0,1'],
            'description' => ['nullable', 'string'],
            'stoppage_ids' => [$creating ? 'nullable' : 'sometimes', 'array'],
            'stoppage_ids.*' => ['integer', 'exists:boat_stoppages,id'],
        ]);
    }

    /**
     * @param  list<int|string>  $stoppageIds
     */
    private function syncStops(BoatTripPackage $package, array $stoppageIds): void
    {
        BoatTripPackageStop::query()->where('package_id', $package->id)->delete();
        foreach (array_values($stoppageIds) as $i => $stoppageId) {
            BoatTripPackageStop::create([
                'package_id' => $package->id,
                'stoppage_id' => (int) $stoppageId,
                'sort_order' => $i,
            ]);
        }
    }
}
