<?php

namespace App\Http\Controllers\Api\v1\Merchant;

use App\Models\Customer;
use App\Models\HotelHold;
use App\Services\ApiIdempotencyService;
use App\Services\Hotel\MerchantHotelHoldService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
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

        try {
            $roomsPayload = $this->holds->consumeHoldForConfirm($hold);
        } catch (\RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        $guestName = trim((string) $request->input('guest_name'));
        $guestMobile = trim((string) $request->input('guest_mobile'));
        $customer = Customer::firstOrNew(['mobile' => $guestMobile]);
        if (! $customer->id) {
            $customer->name = $guestName;
            $customer->mobile = $guestMobile;
            $customer->password = Str::random(8);
            $customer->save();
        } elseif ($guestName !== '' && blank($customer->name)) {
            $customer->name = $guestName;
            $customer->save();
        }

        $quote = is_array($hold->quote_json) ? $hold->quote_json : [];
        $payload = [
            'customer_id' => $customer->id,
            'platform' => 'merchant_desk',
            'check_in_date' => $hold->check_in->format('Y-m-d'),
            'check_out_date' => $hold->check_out->format('Y-m-d'),
            'adults' => (int) $hold->adults,
            'children' => (int) $hold->children,
            'rooms' => $roomsPayload,
            'skip_inventory_reserve' => true,
            'hotel_hold_id' => $hold->id,
            'hotel_id' => (int) ($quote['hotel_id'] ?? 0) ?: null,
        ];

        $serviceResponse = $this->bookingService->createWithValidatedData($payload);
        $decoded = json_decode((string) $serviceResponse->getContent(), true);
        $ok = (bool) ($decoded['status'] ?? false);

        if (! $ok) {
            return response()->json([
                'success' => false,
                'message' => (string) ($decoded['message'] ?? 'Booking failed after hold.'),
                'data' => $decoded['data'] ?? null,
            ], 422);
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
