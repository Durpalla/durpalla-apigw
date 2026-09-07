<?php

namespace App\Http\Controllers\Api\v1\Merchant;

use App\Models\Booking;
use App\Services\BoatRental\BoatRentalBookingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MerchantBoatBookingController extends MerchantBoatBaseController
{
    public function __construct(
        private readonly BoatRentalBookingService $bookingService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $ownerId = $this->merchantOwnerId($request);
        $this->assertBoatRentalAllowed($ownerId);

        $request->validate([
            'status' => ['nullable', 'string', 'max:32'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);

        $q = Booking::query()
            ->boatRental()
            ->with(['boatItems.boat', 'customer'])
            ->whereHas('boatItems.boat', fn ($bq) => $bq->where('merchant_id', $ownerId))
            ->orderByDesc('id');

        if ($request->filled('status')) {
            $q->where('status', strtoupper((string) $request->status));
        }

        $paginator = $q->paginate((int) $request->get('per_page', 10));

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

    public function show(Request $request, int $id): JsonResponse
    {
        $ownerId = $this->merchantOwnerId($request);
        $this->assertBoatRentalAllowed($ownerId);

        $booking = Booking::query()
            ->boatRental()
            ->with(['boatItems.boat', 'customer', 'payments'])
            ->whereHas('boatItems.boat', fn ($bq) => $bq->where('merchant_id', $ownerId))
            ->findOrFail($id);

        return response()->json(['success' => true, 'data' => $booking]);
    }

    public function cancel(Request $request, int $id): JsonResponse
    {
        $ownerId = $this->merchantOwnerId($request);
        $this->assertBoatRentalAllowed($ownerId);

        Booking::query()
            ->boatRental()
            ->whereHas('boatItems.boat', fn ($bq) => $bq->where('merchant_id', $ownerId))
            ->findOrFail($id);

        $result = $this->bookingService->cancelBooking($id, $request->input('reason'));

        return response()->json([
            'success' => (bool) ($result['ok'] ?? false),
            'message' => $result['message'] ?? '',
            'data' => $result['data'] ?? null,
        ], ($result['ok'] ?? false) ? 200 : 422);
    }
}
