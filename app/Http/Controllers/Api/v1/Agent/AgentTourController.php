<?php

namespace App\Http\Controllers\Api\v1\Agent;

use App\Http\Controllers\Controller;
use App\Models\Agent;
use App\Services\Tour\TourBookingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AgentTourController extends Controller
{
    public function __construct(
        private readonly TourBookingService $tours,
    ) {}

    public function search(Request $request): JsonResponse
    {
        if (! $this->agent()) {
            return $this->unauthorized();
        }

        return response()->json([
            'success' => true,
            'message' => '',
            'data' => $this->tours->search($request),
        ]);
    }

    public function show(int $id): JsonResponse
    {
        if (! $this->agent()) {
            return $this->unauthorized();
        }

        $data = $this->tours->show($id);
        if ($data === null) {
            return response()->json([
                'success' => false,
                'message' => __('Tour not found'),
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => '',
            'data' => $data,
        ]);
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
            'departure_id' => ['required', 'integer', 'exists:tour_departures,id'],
            'places' => ['nullable', 'integer', 'min:1', 'max:100'],
            'guest' => ['nullable', 'array'],
        ]);

        try {
            $hold = $this->tours->createHold(
                null,
                $validated,
                $idempotencyKey,
                (int) $agent->id,
            );

            return response()->json([
                'success' => true,
                'data' => [
                    'hold_id' => $hold->id,
                    'tour_id' => (int) $hold->tour_id,
                    'departure_id' => (int) $hold->departure_id,
                    'places' => (int) $hold->places,
                    'total' => (float) $hold->total_price,
                    'expires_at' => $hold->expires_at?->toIso8601String(),
                    'status' => $hold->status,
                ],
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
            'hold_id' => ['required', 'integer', 'exists:tour_holds,id'],
            'guest_name' => ['nullable', 'string', 'max:120'],
            'guest_mobile' => ['nullable', 'string', 'max:32'],
            'guest_email' => ['nullable', 'email', 'max:120'],
        ]);

        try {
            $result = $this->tours->confirmFromHold(
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
                    'tour' => [
                        'id' => (int) $item->tour_id,
                        'title' => $item->tour_title,
                        'depart_date' => $item->depart_date?->toDateString(),
                        'places' => (int) $item->places,
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
