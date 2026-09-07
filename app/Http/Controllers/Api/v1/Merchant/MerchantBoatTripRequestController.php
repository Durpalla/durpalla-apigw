<?php

namespace App\Http\Controllers\Api\v1\Merchant;

use App\Services\BoatRental\BoatTripBidService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MerchantBoatTripRequestController extends MerchantBoatBaseController
{
    public function __construct(
        private readonly BoatTripBidService $bids,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $ownerId = $this->merchantOwnerId($request);
        $this->assertBoatRentalAllowed($ownerId);

        $cityId = $request->filled('city_id') ? (int) $request->input('city_id') : null;

        return response()->json([
            'success' => true,
            'data' => $this->bids->listOpenRequestsForMerchant($ownerId, $cityId),
        ]);
    }

    public function placeBid(Request $request, int $requestId): JsonResponse
    {
        $ownerId = $this->merchantOwnerId($request);
        $this->assertBoatRentalAllowed($ownerId);

        $validated = $request->validate([
            'boat_id' => ['required', 'integer', 'exists:boats,id'],
            'amount' => ['required', 'numeric', 'min:0'],
            'message' => ['nullable', 'string', 'max:2000'],
        ]);

        try {
            $bid = $this->bids->placeBid($ownerId, $requestId, $validated);

            return response()->json([
                'success' => true,
                'data' => $this->bids->bidPayload($bid),
            ], 201);
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function updateBid(Request $request, int $bidId): JsonResponse
    {
        $ownerId = $this->merchantOwnerId($request);
        $this->assertBoatRentalAllowed($ownerId);

        $validated = $request->validate([
            'amount' => ['nullable', 'numeric', 'min:0'],
            'message' => ['nullable', 'string', 'max:2000'],
        ]);

        try {
            $bid = $this->bids->updateBid($ownerId, $bidId, $validated);

            return response()->json([
                'success' => true,
                'data' => $this->bids->bidPayload($bid),
            ]);
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function withdrawBid(Request $request, int $bidId): JsonResponse
    {
        $ownerId = $this->merchantOwnerId($request);
        $this->assertBoatRentalAllowed($ownerId);

        $ok = $this->bids->withdrawBid($ownerId, $bidId);

        return response()->json([
            'success' => $ok,
            'message' => $ok ? 'Bid withdrawn.' : 'Bid not found.',
        ], $ok ? 200 : 404);
    }

    public function myBids(Request $request): JsonResponse
    {
        $ownerId = $this->merchantOwnerId($request);
        $this->assertBoatRentalAllowed($ownerId);

        return response()->json([
            'success' => true,
            'data' => $this->bids->listMerchantBids($ownerId),
        ]);
    }
}
