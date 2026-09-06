<?php

namespace App\Http\Controllers\Api\v1\Merchant;

use App\Models\HotelHold;
use App\Services\ApiIdempotencyService;
use App\Services\Hotel\MerchantHotelHoldService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\Hotel;
use App\Services\Hotel\MerchantDeskBookingService;

class MerchantHotelHoldController extends MerchantHotelBaseController
{
    public function __construct(
        private readonly MerchantHotelHoldService $holds,
        private readonly MerchantDeskBookingService $bookingService,
    ) {}

    /**
     * POST /merchant/hotel-holds — soft-hold on shared hotel_holds / hotel_inventory.
     */
    public function store(Request $request): JsonResponse
    {
        $ownerId = $this->merchantOwnerId($request);
        $this->assertHotelAllowed($ownerId);

        $idempotency = app(ApiIdempotencyService::class);
        $idemKey = $idempotency->keyFromRequest();
        $actor = $request->user();
        $actorId = (int) ($actor?->id ?? 0);

        if ($idemKey === '' || ! $idempotency->isValidKey($idemKey)) {
            return response()->json([
                'success' => false,
                'message' => 'Idempotency-Key header is required (1–64 characters).',
            ], 422);
        }

        $cached = $idempotency->find('merchant_hotel_hold', $actorId, $idemKey);
        if ($cached) {
            return $cached;
        }

        $validator = Validator::make($request->all(), [
            'hotel_id' => ['required', 'integer', 'exists:hotels,id'],
            'check_in_date' => ['required', 'date'],
            'check_out_date' => ['required', 'date', 'after:check_in_date'],
            'adults' => ['nullable', 'integer', 'min:1'],
            'children' => ['nullable', 'integer', 'min:0'],
            'rooms' => ['required', 'array', 'min:1'],
            'rooms.*.room_id' => ['nullable', 'integer', 'exists:hotel_rooms,id'],
            'rooms.*.hotel_room_id' => ['nullable', 'integer', 'exists:hotel_rooms,id'],
            'rooms.*.room_type_id' => ['nullable', 'integer', 'exists:room_types,id'],
            'rooms.*.rate_plan_id' => ['nullable', 'integer', 'exists:room_rate_plans,id'],
            'rooms.*.quantity' => ['nullable', 'integer', 'min:1', 'max:20'],
            'rooms.*.adults' => ['nullable', 'integer', 'min:1'],
            'rooms.*.children' => ['nullable', 'integer', 'min:0'],
            'rooms.*.children_ages' => ['nullable', 'array'],
            'rooms.*.children_ages.*' => ['integer', 'min:0', 'max:17'],
            'children_ages' => ['nullable', 'array'],
            'children_ages.*' => ['integer', 'min:0', 'max:17'],
        ]);
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors(),
            ], 422);
        }

        $hotelId = (int) $request->input('hotel_id');
        if (! Hotel::query()->where('id', $hotelId)->where('merchant_id', $ownerId)->exists()) {
            return response()->json(['success' => false, 'message' => 'Hotel not found for this merchant.'], 404);
        }

        try {
            $hold = $this->holds->createHold(
                $ownerId,
                $actorId ?: null,
                $actor ? $actor::class : null,
                $validator->validated(),
                $idemKey,
            );
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        $body = [
            'success' => true,
            'message' => 'Rooms held until '.$hold->expires_at?->toIso8601String(),
            'data' => $this->serializeHold($hold),
        ];
        $idempotency->remember('merchant_hotel_hold', $actorId, $idemKey, $body, 201, (int) $hold->id);

        return response()->json($body, 201);
    }

    public function index(Request $request): JsonResponse
    {
        $ownerId = $this->merchantOwnerId($request);
        $this->assertHotelAllowed($ownerId);
        if (! \Illuminate\Support\Facades\Schema::hasTable('hotel_holds')) {
            return response()->json(['success' => true, 'data' => []]);
        }
        $this->holds->expireStaleForOwner($ownerId);

        $holds = HotelHold::query()
            ->where('merchant_owner_id', $ownerId)
            ->where('status', HotelHold::STATUS_PENDING)
            ->where('expires_at', '>', now())
            ->orderByDesc('id')
            ->limit(50)
            ->get()
            ->map(fn (HotelHold $h) => $this->serializeHold($h))
            ->values()
            ->all();

        return response()->json(['success' => true, 'data' => $holds]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $ownerId = $this->merchantOwnerId($request);
        $this->assertHotelAllowed($ownerId);

        $hold = HotelHold::query()
            ->where('merchant_owner_id', $ownerId)
            ->findOrFail($id);

        $hold = $this->holds->releaseHold($hold);

        return response()->json([
            'success' => true,
            'message' => 'Hold released.',
            'data' => $this->serializeHold($hold),
        ]);
    }

    public function confirm(Request $request, int $id): JsonResponse
    {
        $ownerId = $this->merchantOwnerId($request);
        $this->assertHotelAllowed($ownerId);

        $idempotency = app(ApiIdempotencyService::class);
        $idemKey = $idempotency->keyFromRequest();
        $actorId = (int) ($request->user()->id ?? 0);
        if ($idemKey === '' || ! $idempotency->isValidKey($idemKey)) {
            return response()->json([
                'success' => false,
                'message' => 'Idempotency-Key header is required (1–64 characters).',
            ], 422);
        }
        $cached = $idempotency->find('merchant_hotel_confirm', $actorId, $idemKey);
        if ($cached) {
            return $cached;
        }

        $validator = Validator::make($request->all(), [
            'guest_name' => ['required', 'string', 'max:191'],
            'guest_mobile' => ['required', 'string', 'max:64'],
            'guest_email' => ['nullable', 'email', 'max:191'],
            'payment.mode' => ['nullable', 'string', 'in:full,partial,none'],
            'payment.method' => ['nullable', 'string', 'max:32'],
            'payment.amountPaid' => ['nullable', 'numeric', 'min:0', 'required_if:payment.mode,partial'],
            'payment.transaction_id' => ['nullable', 'string', 'max:128'],
            'payment.account_no' => ['nullable', 'string', 'max:64'],
            'payment.remarks' => ['nullable', 'string', 'max:500'],
            'payment.amount_paid' => ['nullable', 'numeric', 'min:0'],
        ]);
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        $hold = HotelHold::query()
            ->where('merchant_owner_id', $ownerId)
            ->findOrFail($id);

        $guestName = trim((string) $request->input('guest_name'));
        $guestMobile = trim((string) $request->input('guest_mobile'));
        $guestEmail = trim((string) $request->input('guest_email', ''));
        $payment = $request->input('payment');

        try {
            $decoded = $this->holds->confirmWithBooking(
                $hold,
                function (HotelHold $lockedHold, array $roomsPayload) use ($guestName, $guestMobile, $guestEmail, $payment) {
                    $quote = is_array($lockedHold->quote_json) ? $lockedHold->quote_json : [];
                    $payload = [
                        'customer_id' => null,
                        'guest_name' => $guestName,
                        'guest_mobile' => $guestMobile,
                        'guest_email' => $guestEmail !== '' ? $guestEmail : null,
                        'platform' => 'merchant_desk',
                        'check_in_date' => $lockedHold->check_in->format('Y-m-d'),
                        'check_out_date' => $lockedHold->check_out->format('Y-m-d'),
                        'adults' => (int) $lockedHold->adults,
                        'children' => (int) $lockedHold->children,
                        'rooms' => $roomsPayload,
                        // Hold still owns units_held until finalize in the same outer transaction.
                        'skip_inventory_reserve' => true,
                        'hotel_hold_id' => $lockedHold->id,
                        'hotel_id' => (int) ($quote['hotel_id'] ?? 0) ?: null,
                        'payment' => is_array($payment) ? $payment : ['mode' => 'none'],
                    ];

                    $serviceResponse = $this->bookingService->createWithValidatedData($payload);
                    $decoded = json_decode((string) $serviceResponse->getContent(), true);
                    if (! (bool) ($decoded['status'] ?? false)) {
                        // Throw so the outer transaction rolls back booking + hold finalize.
                        throw new \RuntimeException(
                            (string) ($decoded['message'] ?? 'Booking failed after hold.')
                        );
                    }

                    return $decoded;
                }
            );
        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('merchant_hold_confirm_failed', [
                'hold_id' => $hold->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unable to confirm hold right now. Please try again.',
            ], 500);
        }

        $body = [
            'success' => true,
            'message' => 'Hotel booking confirmed from hold.',
            'data' => $decoded['data'] ?? null,
            'hold_id' => $hold->id,
        ];
        $resourceId = (int) (data_get($decoded, 'data.id') ?? 0) ?: null;
        $idempotency->remember('merchant_hotel_confirm', $actorId, $idemKey, $body, 201, $resourceId);

        return response()->json($body, 201);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeHold(HotelHold $hold): array
    {
        $quote = is_array($hold->quote_json) ? $hold->quote_json : [];
        $lines = is_array($quote['lines'] ?? null) ? $quote['lines'] : [];

        return [
            'id' => $hold->id,
            'hotel_id' => (int) ($quote['hotel_id'] ?? 0) ?: null,
            'check_in' => $hold->check_in?->format('Y-m-d'),
            'check_out' => $hold->check_out?->format('Y-m-d'),
            'adults' => (int) $hold->adults,
            'children' => (int) $hold->children,
            'status' => $hold->status,
            'expires_at' => $hold->expires_at?->toIso8601String(),
            'total_amount' => (float) $hold->total_amount,
            'lines' => $lines,
        ];
    }
}
