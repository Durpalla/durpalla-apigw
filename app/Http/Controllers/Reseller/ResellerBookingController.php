<?php

namespace App\Http\Controllers\Reseller;

use App\Exceptions\InsufficientWalletBalanceException;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Services\ResellerBookingService;
use App\Services\ResellerCancellationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Machine-to-machine booking API for API-partner (reseller) parties. Bookings
 * are confirmed and settled instantly from the reseller's prepaid wallet.
 */
class ResellerBookingController extends Controller
{
    public function __construct(
        private readonly ResellerBookingService $bookings,
        private readonly ResellerCancellationService $cancellations,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $partner = $request->attributes->get('api_partner');

        $perPage = min(100, max(1, (int) $request->input('per_page', 20)));

        $query = Booking::where('party_id', $partner->id);

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }
        if ($request->filled('from')) {
            $query->whereDate('booking_date', '>=', $request->input('from'));
        }
        if ($request->filled('to')) {
            $query->whereDate('booking_date', '<=', $request->input('to'));
        }

        $bookings = $query->with(['bookingItems', 'customer', 'payment'])
            ->orderByDesc('id')
            ->paginate($perPage);

        return response()->json(['success' => true, 'data' => $bookings]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'customer_name' => 'nullable|string|max:191',
            'customer_mobile' => 'required|string|max:32',
            'customer_email' => 'nullable|email',
            'items' => 'required|array|min:1',
            'items.*.item_id' => 'required|integer',
        ]);

        $partner = $request->attributes->get('api_partner');

        try {
            $booking = $this->bookings->create($partner, $data);
        } catch (InsufficientWalletBalanceException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 402);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (\RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 409);
        }

        return response()->json([
            'success' => true,
            'message' => 'Booking confirmed.',
            'data' => $booking,
        ], 201);
    }

    public function show(Request $request, Booking $booking): JsonResponse
    {
        $partner = $request->attributes->get('api_partner');
        if ((int) $booking->party_id !== (int) $partner->id) {
            abort(404);
        }

        return response()->json([
            'success' => true,
            'data' => $booking->load(['bookingItems', 'customer', 'payment']),
        ]);
    }

    public function cancel(Request $request, Booking $booking): JsonResponse
    {
        $partner = $request->attributes->get('api_partner');
        if ((int) $booking->party_id !== (int) $partner->id) {
            abort(404);
        }

        try {
            $result = $this->cancellations->cancel($partner, $booking);
        } catch (\RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 409);
        }

        return response()->json([
            'success' => true,
            'message' => 'Booking cancelled.',
            'data' => [
                'booking' => $result['booking'],
                'refund_percent' => $result['refund_percent'],
                'refund_amount' => $result['refund_amount'],
                'wallet_debit_amount' => $result['wallet_debit_amount'],
            ],
        ]);
    }
}
