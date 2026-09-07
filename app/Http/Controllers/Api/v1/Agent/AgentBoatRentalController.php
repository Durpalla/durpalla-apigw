<?php

namespace App\Http\Controllers\Api\v1\Agent;

use App\Http\Controllers\Controller;
use App\Models\Agent;
use App\Services\BoatRental\BoatRentalBookingService;
use App\Services\BoatRental\BoatTripBidService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AgentBoatRentalController extends Controller
{
    public function __construct(
        private readonly BoatRentalBookingService $boats,
        private readonly BoatTripBidService $bids,
    ) {}

    public function search(Request $request): JsonResponse
    {
        if (! $this->agent()) {
            return $this->unauthorized();
        }

        return response()->json([
            'success' => true,
            'message' => '',
            'data' => $this->boats->search($request),
        ]);
    }

    public function show(int $id): JsonResponse
    {
        if (! $this->agent()) {
            return $this->unauthorized();
        }

        $data = $this->boats->show($id);
        if ($data === null) {
            return response()->json([
                'success' => false,
                'message' => __('Boat not found'),
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => '',
            'data' => $data,
        ]);
    }

    public function quote(Request $request): JsonResponse
    {
        if (! $this->agent()) {
            return $this->unauthorized();
        }

        $request->validate([
            'boat_id' => ['required', 'integer', 'exists:boats,id'],
            'rental_mode' => ['required', 'string', 'in:hourly,daily,package'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'guests' => ['nullable', 'integer', 'min:1', 'max:500'],
            'package_id' => ['nullable', 'integer', 'required_if:rental_mode,package'],
        ]);

        try {
            return response()->json([
                'success' => true,
                'data' => $this->boats->quote($request),
            ]);
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
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

        $validated = $request->validate([
            'boat_id' => ['required', 'integer', 'exists:boats,id'],
            'rental_mode' => ['required', 'string', 'in:hourly,daily,package'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'guests' => ['nullable', 'integer', 'min:1', 'max:500'],
            'package_id' => ['nullable', 'integer', 'required_if:rental_mode,package'],
            'guest' => ['nullable', 'array'],
        ]);

        try {
            $hold = $this->boats->createHold(
                null,
                $validated,
                $idempotencyKey,
                (int) $agent->id,
            );

            return response()->json([
                'success' => true,
                'data' => $this->boats->holdPayload($hold),
            ]);
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function confirm(Request $request): JsonResponse
    {
        $agent = $this->agent();
        if (! $agent) {
            return $this->unauthorized();
        }

        $request->validate([
            'hold_id' => ['required', 'integer', 'exists:boat_holds,id'],
            'guest_name' => ['nullable', 'string', 'max:120'],
            'guest_mobile' => ['nullable', 'string', 'max:32'],
            'guest_email' => ['nullable', 'email', 'max:120'],
        ]);

        try {
            $result = $this->boats->confirmFromHold(
                null,
                (int) $request->input('hold_id'),
                'agent_app',
                [
                    'name' => (string) $request->input('guest_name', ''),
                    'mobile' => (string) $request->input('guest_mobile', ''),
                    'email' => (string) $request->input('guest_email', ''),
                ],
                (int) $agent->id,
            );

            $booking = $result['booking'];
            $payment = $result['payment'];
            $item = $result['item'];

            return response()->json([
                'success' => true,
                'data' => [
                    'booking_id' => $booking->id,
                    'payment_id' => $payment->id,
                    'transaction_id' => $payment->transaction_id,
                    'total_payable' => (float) $booking->total_payable,
                    'status' => $booking->status,
                    'boat' => [
                        'id' => (int) $item->boat_id,
                        'title' => $item->boat_title,
                        'rental_mode' => $item->rental_mode,
                        'starts_at' => $item->starts_at?->toIso8601String(),
                        'ends_at' => $item->ends_at?->toIso8601String(),
                        'guests' => (int) $item->guests,
                    ],
                ],
            ]);
        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function stoppages(Request $request): JsonResponse
    {
        if (! $this->agent()) {
            return $this->unauthorized();
        }

        return response()->json([
            'success' => true,
            'data' => $this->bids->listStoppages($request),
        ]);
    }

    public function createRequest(Request $request): JsonResponse
    {
        $agent = $this->agent();
        if (! $agent) {
            return $this->unauthorized();
        }

        $validated = $request->validate([
            'stoppage_ids' => ['required', 'array', 'min:1'],
            'stoppage_ids.*' => ['integer', 'exists:boat_stoppages,id'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'guests' => ['required', 'integer', 'min:1', 'max:500'],
            'city_id' => ['nullable', 'integer'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        try {
            $tripRequest = $this->bids->createRequest(null, $validated, (int) $agent->id);

            return response()->json([
                'success' => true,
                'data' => $this->bids->requestPayload($tripRequest),
            ], 201);
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function showRequest(int $id): JsonResponse
    {
        $agent = $this->agent();
        if (! $agent) {
            return $this->unauthorized();
        }

        $data = $this->bids->showRequest($id, null, (int) $agent->id);
        if ($data === null) {
            return response()->json(['success' => false, 'message' => __('Not found')], 404);
        }

        return response()->json(['success' => true, 'data' => $data]);
    }

    public function listBids(int $id): JsonResponse
    {
        $agent = $this->agent();
        if (! $agent) {
            return $this->unauthorized();
        }

        try {
            return response()->json([
                'success' => true,
                'data' => $this->bids->listBidsForRequest($id, null, (int) $agent->id),
            ]);
        } catch (\RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 403);
        }
    }

    public function acceptBid(Request $request, int $id): JsonResponse
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

        try {
            $hold = $this->bids->acceptBid(null, $id, (int) $agent->id, $idempotencyKey);

            return response()->json([
                'success' => true,
                'data' => $this->boats->holdPayload($hold),
            ]);
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    private function agent(): ?Agent
    {
        $user = Auth::guard('agent')->user() ?? Auth::user();

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
