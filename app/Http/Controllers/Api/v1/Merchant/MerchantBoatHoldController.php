<?php

namespace App\Http\Controllers\Api\v1\Merchant;

use App\Models\BoatHold;
use App\Services\ApiIdempotencyService;
use App\Services\BoatRental\BoatRentalBookingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class MerchantBoatHoldController extends MerchantBoatBaseController
{
    public function __construct(
        private readonly BoatRentalBookingService $boats,
    ) {}

    public function store(Request $request): JsonResponse
    {
        $ownerId = $this->merchantOwnerId($request);
        $this->assertBoatRentalAllowed($ownerId);

        $idempotency = app(ApiIdempotencyService::class);
        $idemKey = $idempotency->keyFromRequest();
        $actorId = (int) ($request->user()->id ?? 0);
        if ($idemKey === '' || ! $idempotency->isValidKey($idemKey)) {
            return response()->json([
                'success' => false,
                'message' => 'Idempotency-Key header is required (1–64 characters).',
            ], 422);
        }
        $cached = $idempotency->find('merchant_boat_hold', $actorId, $idemKey);
        if ($cached) {
            return $cached;
        }

        $validator = Validator::make($request->all(), [
            'boat_id' => ['required', 'integer'],
            'rental_mode' => ['required', 'string', 'in:hourly,daily,package'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'guests' => ['required', 'integer', 'min:1', 'max:500'],
            'package_id' => ['nullable', 'integer', 'required_if:rental_mode,package'],
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
            $hold = $this->boats->createHold(
                null,
                [
                    'boat_id' => (int) $request->input('boat_id'),
                    'rental_mode' => (string) $request->input('rental_mode'),
                    'starts_at' => (string) $request->input('starts_at'),
                    'ends_at' => (string) $request->input('ends_at'),
                    'guests' => (int) $request->input('guests'),
                    'package_id' => $request->input('package_id'),
                    'guest' => $guest !== [] ? $guest : null,
                ],
                $idemKey,
                null,
                $ownerId,
            );
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage() ?: 'Unable to hold boat.',
            ], 422);
        }

        $payload = [
            'success' => true,
            'message' => 'Boat held.',
            'data' => $this->boats->holdPayload($hold),
        ];
        $idempotency->remember('merchant_boat_hold', $actorId, $idemKey, $payload, 201, (int) $hold->id);

        return response()->json($payload, 201);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $ownerId = $this->merchantOwnerId($request);
        $this->assertBoatRentalAllowed($ownerId);

        $ok = $this->boats->releaseHold(null, $id, null, $ownerId);

        return response()->json([
            'success' => $ok,
            'message' => $ok ? 'Hold released.' : 'Hold not found or already released.',
        ], $ok ? 200 : 404);
    }

    public function confirm(Request $request, int $id): JsonResponse
    {
        $ownerId = $this->merchantOwnerId($request);
        $this->assertBoatRentalAllowed($ownerId);

        $idempotency = app(ApiIdempotencyService::class);
        $idemKey = $idempotency->keyFromRequest();
        $actorId = (int) ($request->user()->id ?? 0);
        if ($idemKey === '' || ! $idempotency->isValidKey($idemKey)) {
            return response()->json([
                'success' => false,
                'message' => 'Idempotency-Key header is required (1–64 characters).',
            ], 422);
        }
        $cached = $idempotency->find('merchant_boat_confirm', $actorId, $idemKey);
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

        BoatHold::query()
            ->where('merchant_id', $ownerId)
            ->findOrFail($id);

        try {
            $result = $this->boats->confirmFromHold(
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
            'message' => 'Boat booking confirmed.',
            'data' => [
                'booking_id' => $result['booking']->id,
                'payment_id' => $result['payment']->id,
                'status' => $result['booking']->status,
                'total_payable' => (float) $result['booking']->total_payable,
                'item' => $result['item'],
            ],
        ];
        $idempotency->remember('merchant_boat_confirm', $actorId, $idemKey, $payload, 201, (int) $result['booking']->id);

        return response()->json($payload, 201);
    }
}
