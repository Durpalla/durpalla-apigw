<?php

namespace App\Http\Controllers\Api\v1\Merchant;

use App\Models\TourHold;
use App\Services\ApiIdempotencyService;
use App\Services\Tour\TourBookingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class MerchantTourHoldController extends MerchantTourBaseController
{
    public function __construct(
        private readonly TourBookingService $tours,
    ) {}

    public function store(Request $request): JsonResponse
    {
        $ownerId = $this->merchantOwnerId($request);
        $this->assertTourAllowed($ownerId);

        $idempotency = app(ApiIdempotencyService::class);
        $idemKey = $idempotency->keyFromRequest();
        $actorId = (int) ($request->user()->id ?? 0);
        if ($idemKey === '' || ! $idempotency->isValidKey($idemKey)) {
            return response()->json([
                'success' => false,
                'message' => 'Idempotency-Key header is required (1–64 characters).',
            ], 422);
        }
        $cached = $idempotency->find('merchant_tour_hold', $actorId, $idemKey);
        if ($cached) {
            return $cached;
        }

        $validator = Validator::make($request->all(), [
            'tour_id' => ['nullable', 'integer'],
            'departure_id' => ['required', 'integer'],
            'places' => ['required', 'integer', 'min:1', 'max:100'],
            'guest_name' => ['nullable', 'string', 'max:191'],
            'guest_mobile' => ['nullable', 'string', 'max:64'],
            'guest_email' => ['nullable', 'email', 'max:191'],
        ]);
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        $guest = array_filter([
            'name' => trim((string) $request->input('guest_name', '')),
            'mobile' => trim((string) $request->input('guest_mobile', '')),
            'email' => trim((string) $request->input('guest_email', '')),
        ], static fn ($v) => $v !== '');

        try {
            $hold = $this->tours->createHold(
                null,
                [
                    'departure_id' => (int) $request->input('departure_id'),
                    'places' => (int) $request->input('places'),
                    'guest' => $guest !== [] ? $guest : null,
                ],
                $idemKey,
                null,
                $ownerId,
            );
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage() ?: 'Unable to hold places.',
            ], 422);
        }

        $payload = [
            'success' => true,
            'message' => 'Places held.',
            'data' => [
                'id' => $hold->id,
                'hold_id' => $hold->id,
                'tour_id' => $hold->tour_id,
                'departure_id' => $hold->departure_id,
                'places' => $hold->places,
                'unit_price' => (float) $hold->unit_price,
                'total_price' => (float) $hold->total_price,
                'expires_at' => $hold->expires_at?->toIso8601String(),
                'status' => $hold->status,
            ],
        ];
        $idempotency->remember('merchant_tour_hold', $actorId, $idemKey, $payload, 201, (int) $hold->id);

        return response()->json($payload, 201);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $ownerId = $this->merchantOwnerId($request);
        $this->assertTourAllowed($ownerId);

        $ok = $this->tours->releaseHold(null, $id, null, $ownerId);

        return response()->json([
            'success' => $ok,
            'message' => $ok ? 'Hold released.' : 'Hold not found or already released.',
        ], $ok ? 200 : 404);
    }

    public function confirm(Request $request, int $id): JsonResponse
    {
        $ownerId = $this->merchantOwnerId($request);
        $this->assertTourAllowed($ownerId);

        $idempotency = app(ApiIdempotencyService::class);
        $idemKey = $idempotency->keyFromRequest();
        $actorId = (int) ($request->user()->id ?? 0);
        if ($idemKey === '' || ! $idempotency->isValidKey($idemKey)) {
            return response()->json([
                'success' => false,
                'message' => 'Idempotency-Key header is required (1–64 characters).',
            ], 422);
        }
        $cached = $idempotency->find('merchant_tour_confirm', $actorId, $idemKey);
        if ($cached) {
            return $cached;
        }

        $validator = Validator::make($request->all(), [
            'guest_name' => ['required', 'string', 'max:191'],
            'guest_mobile' => ['required', 'string', 'max:64'],
            'guest_email' => ['nullable', 'email', 'max:191'],
            'payment.mode' => ['nullable', 'string', 'in:full,partial,none'],
            'payment.method' => ['nullable', 'string', 'max:32'],
            'payment.amount_paid' => ['nullable', 'numeric', 'min:0'],
            'payment.amountPaid' => ['nullable', 'numeric', 'min:0'],
        ]);
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        TourHold::query()
            ->where('merchant_id', $ownerId)
            ->findOrFail($id);

        try {
            $result = $this->tours->confirmFromHold(
                null,
                $id,
                'merchant_desk',
                [
                    'name' => trim((string) $request->input('guest_name')),
                    'mobile' => trim((string) $request->input('guest_mobile')),
                    'email' => trim((string) $request->input('guest_email', '')),
                ],
                null,
                $ownerId,
                is_array($request->input('payment')) ? $request->input('payment') : ['mode' => 'full', 'method' => 'cash'],
            );
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage() ?: 'Unable to confirm booking.',
            ], 422);
        }

        $payload = [
            'success' => true,
            'message' => 'Tour booking confirmed.',
            'data' => [
                'booking_id' => $result['booking']->id,
                'payment_id' => $result['payment']->id,
                'status' => $result['booking']->status,
                'total_payable' => (float) $result['booking']->total_payable,
                'item' => $result['item'],
            ],
        ];
        $idempotency->remember('merchant_tour_confirm', $actorId, $idemKey, $payload, 201, (int) $result['booking']->id);

        return response()->json($payload, 201);
    }
}
