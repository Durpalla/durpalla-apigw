<?php

namespace App\Http\Controllers\Api\v1\Merchant;

use App\Models\Booking;
use App\Services\Tour\TourBookingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MerchantTourBookingController extends MerchantTourBaseController
{
    public function __construct(
        private readonly TourBookingService $bookingService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $ownerId = $this->merchantOwnerId($request);
        $this->assertTourAllowed($ownerId);

        $request->validate([
            'status' => ['nullable', 'string', 'max:32'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);

        $q = Booking::query()
            ->tour()
            ->with(['tourItems.tour', 'customer'])
            ->whereHas('tourItems.tour', fn ($tq) => $tq->where('merchant_id', $ownerId))
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
        $this->assertTourAllowed($ownerId);

        $booking = Booking::query()
            ->tour()
            ->with(['tourItems.tour', 'tourItems.departure', 'customer', 'payments'])
            ->whereHas('tourItems.tour', fn ($tq) => $tq->where('merchant_id', $ownerId))
            ->findOrFail($id);

        return response()->json(['success' => true, 'data' => $booking]);
    }

    public function cancel(Request $request, int $id): JsonResponse
    {
        $ownerId = $this->merchantOwnerId($request);
        $this->assertTourAllowed($ownerId);

        Booking::query()
            ->tour()
            ->whereHas('tourItems.tour', fn ($tq) => $tq->where('merchant_id', $ownerId))
            ->findOrFail($id);

        $result = $this->bookingService->cancelBooking($id, $request->input('reason'));

        return response()->json([
            'success' => (bool) ($result['ok'] ?? false),
            'message' => $result['message'] ?? '',
            'data' => $result['data'] ?? null,
        ], ($result['ok'] ?? false) ? 200 : 422);
    }
}
