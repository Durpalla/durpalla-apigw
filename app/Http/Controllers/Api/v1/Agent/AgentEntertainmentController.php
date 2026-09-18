<?php

namespace App\Http\Controllers\Api\v1\Agent;

use App\Http\Controllers\Controller;
use App\Models\Agent;
use App\Services\Entertainment\EntertainmentBookingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class AgentEntertainmentController extends Controller
{
    public function __construct(
        private readonly EntertainmentBookingService $entertainment,
    ) {}

    public function search(Request $request): JsonResponse
    {
        if (! $this->agent()) {
            return $this->unauthorized();
        }

        return response()->json([
            'success' => true,
            'message' => '',
            'data' => $this->entertainment->search($request),
        ]);
    }

    public function show(int $id): JsonResponse
    {
        if (! $this->agent()) {
            return $this->unauthorized();
        }

        $data = $this->entertainment->show($id);
        if ($data === null) {
            return response()->json(['success' => false, 'message' => __('Venue not found')], 404);
        }

        return response()->json(['success' => true, 'message' => '', 'data' => $data]);
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
            'venue_id' => ['required', 'integer'],
            'visit_date' => ['nullable', 'date'],
            'slot_template_id' => ['nullable', 'integer'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.ticket_type_id' => ['required', 'integer'],
            'lines.*.qty' => ['nullable', 'integer', 'min:1', 'max:50'],
            'guest' => ['nullable', 'array'],
        ]);

        try {
            $hold = $this->entertainment->createHold(
                null,
                $validated,
                $idempotencyKey,
                (int) $agent->id,
            );

            return response()->json([
                'success' => true,
                'data' => $this->entertainment->formatHold($hold),
            ]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function confirm(Request $request): JsonResponse
    {
        $agent = $this->agent();
        if (! $agent) {
            return $this->unauthorized();
        }

        $validated = $request->validate([
            'hold_id' => ['required', 'integer'],
            'guest' => ['nullable', 'array'],
            'payment' => ['nullable', 'array'],
        ]);

        try {
            $result = $this->entertainment->confirmFromHold(
                null,
                (int) $validated['hold_id'],
                'agent',
                $validated['guest'] ?? null,
                (int) $agent->id,
                null,
                array_merge(['mode' => 'full'], $validated['payment'] ?? []),
            );

            return response()->json([
                'success' => true,
                'data' => [
                    'booking_id' => $result['booking']->id,
                    'total_payable' => (float) $result['booking']->total_payable,
                    'payment_status' => $result['payment']->status,
                    'tickets' => array_map(
                        fn ($t) => $this->entertainment->ticketPayload($t),
                        $result['tickets'],
                    ),
                ],
            ]);
        } catch (\Throwable $e) {
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
        return response()->json(['success' => false, 'message' => __('Unauthorized')], 401);
    }
}
