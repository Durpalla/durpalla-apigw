<?php

namespace App\Http\Controllers\Api\v1\Agent;

use App\Http\Controllers\Controller;
use App\Models\Agent;
use App\Services\AgentCounterPaymentService;
use App\Services\AgentHotelBookingService;
use App\Services\AgentHotelSearchService;
use App\Services\AgentPaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AgentHotelController extends Controller
{
    public function __construct(
        private readonly AgentHotelSearchService $hotels,
        private readonly AgentHotelBookingService $booking,
        private readonly AgentCounterPaymentService $counterPayments,
        private readonly AgentPaymentService $agentPayments,
    ) {
    }

    /**
     * Default: favourite hotels. With city/q (or mode=search), search takes over; favourites stay highlighted.
     * Search requires check_in and check_out (Y-m-d, check_out > check_in).
     */
    public function index(Request $request): JsonResponse
    {
        $agent = $this->agent();
        if (! $agent) {
            return $this->unauthorized();
        }

        $city = trim((string) $request->input('city', $request->input('q', '')));
        $explicitMode = strtolower(trim((string) $request->input('mode', '')));
        $isSearch = $explicitMode === 'search'
            || ($explicitMode !== 'favourites' && $city !== '');

        if ($isSearch) {
            $request->validate([
                'check_in' => ['required', 'date_format:Y-m-d'],
                'check_out' => ['required', 'date_format:Y-m-d', 'after:check_in'],
            ], [
                'check_in.required' => __('Check-in date is required'),
                'check_out.required' => __('Check-out date is required'),
                'check_out.after' => __('Check-out must be after check-in'),
            ]);
        }

        $result = $this->hotels->list($agent, $request->only(['city', 'q', 'check_in', 'check_out', 'mode']));

        return response()->json([
            'success' => true,
            'message' => '',
            'data' => $result['items'],
            'meta' => $result['meta'],
        ]);
    }

    public function citySuggest(Request $request): JsonResponse
    {
        $term = trim((string) $request->query('q', $request->query('term', '')));
        if (! Schema::hasTable('cities')) {
            return response()->json(['success' => true, 'data' => []]);
        }

        $query = DB::table('cities')->select('id', 'name')->orderBy('name')->limit(30);
        if ($term !== '') {
            $query->where('name', 'like', '%'.$term.'%');
        }

        return response()->json([
            'success' => true,
            'data' => $query->get(),
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $agent = $this->agent();
        if (! $agent) {
            return $this->unauthorized();
        }

        try {
            $data = $this->hotels->detail($agent, $id);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => '',
            'data' => $data,
        ]);
    }

    public function favourites(): JsonResponse
    {
        $agent = $this->agent();
        if (! $agent) {
            return $this->unauthorized();
        }

        $items = $this->hotels->listFavourites($agent);

        return response()->json([
            'success' => true,
            'message' => '',
            'data' => $items,
            'meta' => [
                'has_favourites' => $items->isNotEmpty(),
                'count' => $items->count(),
                'mode' => 'favourites',
            ],
        ]);
    }

    public function addFavourite(Request $request): JsonResponse
    {
        $agent = $this->agent();
        if (! $agent) {
            return $this->unauthorized();
        }

        $request->validate([
            'hotel_id' => 'required|integer|min:1',
        ]);

        try {
            $data = $this->hotels->addFavourite($agent, (int) $request->input('hotel_id'));
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => __('Added to favourites'),
            'data' => $data,
        ]);
    }

    public function removeFavourite(int $hotelId): JsonResponse
    {
        $agent = $this->agent();
        if (! $agent) {
            return $this->unauthorized();
        }

        $this->hotels->removeFavourite($agent, $hotelId);

        return response()->json([
            'success' => true,
            'message' => __('Removed from favourites'),
            'data' => [
                'hotel_id' => $hotelId,
                'is_favourite' => false,
            ],
        ]);
    }

    public function rooms(Request $request, int $id): JsonResponse
    {
        $agent = $this->agent();
        if (! $agent) {
            return $this->unauthorized();
        }

        $request->validate([
            'check_in' => ['required', 'date_format:Y-m-d'],
            'check_out' => ['required', 'date_format:Y-m-d', 'after:check_in'],
            'adults' => ['nullable', 'integer', 'min:1', 'max:8'],
            'children' => ['nullable', 'integer', 'min:0', 'max:6'],
        ]);

        $rooms = $this->booking->roomsForStay($request, $id);
        $payload = [
            'success' => true,
            'data' => $rooms,
        ];
        if ($rooms === []) {
            $payload['message'] = $this->booking->describeEmptyHotelRooms($request, $id) ?? '';
        }

        return response()->json($payload);
    }

    public function hold(Request $request): JsonResponse
    {
        $agent = $this->agent();
        if (! $agent) {
            return $this->unauthorized();
        }

        $idempotencyKey = trim((string) $request->header('Idempotency-Key', ''));
        if ($idempotencyKey === '' || strlen($idempotencyKey) > 64) {
            return response()->json([
                'success' => false,
                'message' => __('Idempotency-Key header is required (max 64 characters)'),
            ], 422);
        }

        $request->validate([
            'room_type_id' => ['required_without:lines', 'integer', 'min:1'],
            'lines' => ['required_without:room_type_id', 'array'],
            'check_in' => ['required', 'date_format:Y-m-d'],
            'check_out' => ['required', 'date_format:Y-m-d', 'after:check_in'],
            'adults' => ['nullable', 'integer', 'min:1', 'max:8'],
            'children' => ['nullable', 'integer', 'min:0', 'max:6'],
        ]);

        try {
            $hold = $this->booking->createHold($agent, $request->all(), $idempotencyKey);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json([
            'success' => true,
            'message' => '',
            'data' => $this->booking->formatHold($hold),
        ]);
    }

    public function paymentMethods(): JsonResponse
    {
        $agent = $this->agent();
        if (! $agent) {
            return $this->unauthorized();
        }

        return response()->json([
            'success' => true,
            'message' => '',
            'data' => $this->counterPayments->availableMethods($agent),
        ]);
    }

    public function confirm(Request $request): JsonResponse
    {
        $agent = $this->agent();
        if (! $agent) {
            return $this->unauthorized();
        }

        $request->validate([
            'hold_id' => ['required', 'integer', 'min:1'],
            'customer_name' => ['required', 'string', 'max:120'],
            'customer_mobile' => ['required', 'string', 'max:32'],
            'payment_method' => ['nullable', 'string', 'max:40'],
            'paid_amount' => ['nullable', 'numeric', 'min:0'],
            'trx_id' => ['nullable', 'string', 'max:120'],
            'gateway_id' => ['nullable', 'integer', 'min:1'],
            'platform' => ['nullable', 'string', 'max:40'],
        ]);

        $method = $this->counterPayments->normalize(
            (string) $request->input('payment_method', AgentCounterPaymentService::METHOD_FUND)
        );
        $liveGateway = $this->counterPayments->isLiveGateway($method, $request->input('gateway_id'));
        if ($liveGateway && ! $request->filled('gateway_id')) {
            return response()->json([
                'success' => false,
                'message' => __('gateway_id is required for digital payment'),
            ], 422);
        }

        try {
            $result = $this->booking->confirmForAgent($agent, (int) $request->input('hold_id'), $request->all());
        } catch (\InvalidArgumentException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (\RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }

        $booking = $result['booking'];
        $payment = $result['payment'];
        $reservation = $result['reservation'];
        $roomType = $reservation->roomType;

        $payload = [
            'success' => true,
            'message' => $liveGateway
                ? __('Complete payment to confirm booking')
                : __('Booking confirmed'),
            'order_id' => $booking->id,
            'booking_id' => $booking->id,
            'requires_payment' => $liveGateway,
            'booking' => [
                'id' => $booking->id,
                'status' => $booking->status,
                'total_payable' => (float) $booking->total_payable,
            ],
            'payment' => [
                'id' => $payment->id ?? null,
                'transaction_id' => $payment->transaction_id ?? null,
                'paid_amount' => (float) ($payment->paid_amount ?? 0),
            ],
            'hotel' => [
                'name' => (string) ($roomType?->title ?? ''),
                'check_in' => $reservation->check_in?->toDateString(),
                'check_out' => $reservation->check_out?->toDateString(),
                'adults' => (int) $reservation->adults,
                'children' => (int) $reservation->children,
            ],
            'trans_id' => $payment->transaction_id ?? null,
            'data' => [
                'booking_id' => $booking->id,
                'hold_id' => (int) $request->input('hold_id'),
                'total_payable' => (float) $booking->total_payable,
            ],
        ];

        if ($liveGateway) {
            $pay = $this->agentPayments->initiate(
                $agent,
                (int) $booking->id,
                (int) $request->input('gateway_id'),
                $request
            );
            $payload['payment'] = array_merge($payload['payment'], $pay);
            if (! empty($pay['paymentURL'])) {
                $payload['paymentURL'] = $pay['paymentURL'];
                $payload['paymentID'] = $pay['paymentID'] ?? null;
            } elseif (empty($pay['success'])) {
                $payload['message'] = $pay['message']
                    ?? __('Booking created but payment could not be started. Retry payment.');
            }
        } else {
            $payload['invoice'] = \Illuminate\Support\Facades\URL::temporarySignedRoute(
                'invoice.download',
                now()->addMinutes(60),
                ['id' => $booking->id]
            );
        }

        return response()->json($payload);
    }

    private function agent(): ?Agent
    {
        $user = auth()->user();

        return $user instanceof Agent ? $user : null;
    }

    private function unauthorized(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => __('Unauthorized'),
        ], 401);
    }
}
