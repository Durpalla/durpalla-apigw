<?php

namespace App\Http\Controllers\Api\v1\Merchant;

use App\Models\Booking;
use App\Models\Customer;
use App\Services\ApiIdempotencyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Modules\Hotel\Entities\Hotel;
use Modules\Hotel\Entities\HotelRoom;
use Modules\Hotel\Entities\RoomRatePlan;
use Modules\Hotel\Http\Requests\HotelBookingCreateRequest;
use Modules\Hotel\Services\HotelBookingService;

class MerchantHotelBookingController extends MerchantHotelBaseController
{
    private HotelBookingService $bookingService;

    public function __construct(HotelBookingService $bookingService)
    {
        $this->bookingService = $bookingService;
    }

    /**
     * POST /api/v1/merchant/hotel-bookings — walk-in / phone booking for merchant-owned hotels.
     *
     * Body: same shape as admin hotel booking create (see HotelBookingCreateRequest) plus:
     * - guest_name (required)
     * - guest_mobile (required) — resolves/creates customer user
     */
    public function store(Request $request): JsonResponse
    {
        $ownerId = $this->merchantOwnerId($request);
        $this->assertHotelAllowed($ownerId);

        $idempotency = app(ApiIdempotencyService::class);
        $idemKey = $idempotency->keyFromRequest();
        $actorId = (int) ($request->user()->id ?? 0);
        if ($idemKey === '' || ! $idempotency->isValidKey($idemKey)) {
            return response()->json([
                'success' => false,
                'message' => __('Idempotency-Key header is required (1–64 characters). Prefer POST /merchant/hotel-holds then confirm.'),
            ], 422);
        }
        if ($actorId > 0) {
            $cached = $idempotency->find('merchant_hotel_booking', $actorId, $idemKey);
            if ($cached) {
                return $cached;
            }
        }

        $rules = (new HotelBookingCreateRequest)->rules();
        $rules['guest_name'] = ['required', 'string', 'max:191'];
        $rules['guest_mobile'] = ['required', 'string', 'max:64'];
        // Merchant desk may send hotel_room_id; we expand it before validating.
        $rules['hotel_id'] = ['nullable', 'integer', 'exists:hotels,id'];
        $rules['rooms.*.hotel_room_id'] = ['nullable', 'integer', 'exists:hotel_rooms,id'];
        $rules['rooms.*.quantity'] = ['nullable', 'integer', 'min:1', 'max:20'];

        $input = $this->expandMerchantRoomRows($request->all(), $ownerId);

        $validator = Validator::make($input, $rules);
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors(),
            ], 422);
        }
        /** @var array<string, mixed> $payload */
        $payload = $validator->validated();

        $guestName = trim((string) ($payload['guest_name'] ?? ''));
        $guestMobile = trim((string) ($payload['guest_mobile'] ?? ''));
        unset($payload['guest_name'], $payload['guest_mobile'], $payload['hotel_id']);

        foreach ($payload['rooms'] ?? [] as $i => $roomRow) {
            unset($payload['rooms'][$i]['hotel_room_id'], $payload['rooms'][$i]['quantity']);
            $hid = (int) ($roomRow['hotel_id'] ?? 0);
            if ($hid <= 0 || ! Hotel::query()->where('id', $hid)->where('merchant_id', $ownerId)->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'One or more hotels are invalid or not owned by this merchant.',
                ], 422);
            }
        }
        $payload['rooms'] = array_values($payload['rooms'] ?? []);

        $customer = Customer::firstOrNew(['mobile' => $guestMobile]);
        if (! $customer->id) {
            $customer->name = $guestName;
            $customer->mobile = $guestMobile;
            $customer->password = Str::random(8);
            $customer->save();
        }

        $payload['customer_id'] = $customer->id;
        $payload['platform'] = 'merchant_desk';

        $serviceResponse = $this->bookingService->createWithValidatedData($payload);
        $decoded = json_decode((string) $serviceResponse->getContent(), true);
        $ok = (bool) ($decoded['status'] ?? false);

        $body = [
            'success' => $ok,
            'message' => (string) ($decoded['message'] ?? ($ok ? 'Hotel booking created.' : 'Booking failed.')),
            'data' => $decoded['data'] ?? null,
        ];
        $status = $ok ? 201 : 422;

        if ($ok && $idemKey !== '' && $actorId > 0) {
            $resourceId = (int) (data_get($decoded, 'data.id') ?? data_get($decoded, 'data.booking.id') ?? 0) ?: null;
            $idempotency->remember('merchant_hotel_booking', $actorId, $idemKey, $body, $status, $resourceId);
        }

        return response()->json($body, $status);
    }

    public function index(Request $request): JsonResponse
    {
        $ownerId = $this->merchantOwnerId($request);
        $this->assertHotelAllowed($ownerId);

        $request->validate([
            'status' => ['nullable', 'string', 'max:64'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
            'q' => ['nullable', 'string', 'max:191'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);

        $q = Booking::query()
            ->hotel()
            ->with(['hotelItems.hotel', 'hotelItems.roomType', 'hotelItems.ratePlan', 'customer', 'supplier'])
            ->whereHas('hotelItems.hotel', function ($hq) use ($ownerId) {
                $hq->where('merchant_id', $ownerId);
            })
            ->orderByDesc('id');

        if ($request->filled('status')) {
            $status = strtoupper(trim((string) $request->status));
            $status = str_replace(['-', ' '], '_', $status);
            $q->where('status', $status);
        }
        if ($request->filled('from')) {
            $q->whereDate('from_date', '>=', $request->from);
        }
        if ($request->filled('to')) {
            $q->whereDate('to_date', '<=', $request->to);
        }
        if ($request->filled('q')) {
            $raw = trim((string) $request->query('q'));
            if ($raw !== '') {
                $pnrService = app(\App\Services\BookingPnrService::class);
                $pnr = $pnrService->normalize($raw);
                if ($pnr !== null) {
                    $q->where('bookings.pnr', $pnr);
                } elseif (preg_match('/^BK-(\d+)$/i', $raw, $m)) {
                    $q->where('bookings.id', (int) $m[1]);
                } elseif (preg_match('/^D\d{6}-/i', $raw)) {
                    $q->where('bookings.pnr', 'like', strtoupper($raw).'%');
                } elseif (ctype_digit($raw)) {
                    $q->where(function ($inner) use ($raw) {
                        $inner->where('bookings.id', (int) $raw)
                            ->orWhere('bookings.pnr', 'like', '%'.$raw.'%');
                    });
                } else {
                    $like = '%'.$raw.'%';
                    $q->where(function ($inner) use ($like, $raw) {
                        $inner->where('bookings.pnr', 'like', strtoupper($raw).'%')
                            ->orWhere('bookings.pnr', 'like', $like)
                            ->orWhereHas('customer', function ($cq) use ($like) {
                                $cq->where('name', 'like', $like)
                                    ->orWhere('mobile', 'like', $like);
                            });
                    });
                }
            }
        }

        $limit = (int) ($request->get('per_page', 50));
        $items = $q->limit($limit)->get();

        return response()->json(['success' => true, 'data' => $items]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $ownerId = $this->merchantOwnerId($request);
        $this->assertHotelAllowed($ownerId);

        $booking = Booking::query()
            ->hotel()
            ->with(['hotelItems.hotel', 'hotelItems.roomType', 'hotelItems.ratePlan', 'customer', 'supplier'])
            ->whereHas('hotelItems.hotel', function ($hq) use ($ownerId) {
                $hq->where('merchant_id', $ownerId);
            })
            ->findOrFail($id);

        return response()->json(['success' => true, 'data' => $booking]);
    }

    public function cancel(Request $request, int $id): JsonResponse
    {
        $ownerId = $this->merchantOwnerId($request);
        $this->assertHotelAllowed($ownerId);

        // Ensure merchant owns the booking hotels before cancelling.
        $booking = Booking::query()
            ->hotel()
            ->with(['hotelItems.hotel'])
            ->whereHas('hotelItems.hotel', function ($hq) use ($ownerId) {
                $hq->where('merchant_id', $ownerId);
            })
            ->findOrFail($id);

        $reason = $request->input('reason');

        // HotelBookingService::cancel uses DB transactions internally; keep call isolated.
        $result = $this->bookingService->cancel($booking->id, $reason);
        $payload = json_decode((string) $result->getContent(), true);

        $ok = (bool) ($payload['status'] ?? false);

        return response()->json([
            'success' => $ok,
            'message' => $payload['message'] ?? ($ok ? 'Cancelled.' : 'Cancel failed.'),
            'data' => $payload['data'] ?? null,
        ], $ok ? 200 : 422);
    }

    public function checkIn(Request $request, int $id): JsonResponse
    {
        $ownerId = $this->merchantOwnerId($request);
        $this->assertHotelAllowed($ownerId);

        $booking = Booking::query()
            ->hotel()
            ->with(['hotelItems.hotel'])
            ->whereHas('hotelItems.hotel', function ($hq) use ($ownerId) {
                $hq->where('merchant_id', $ownerId);
            })
            ->findOrFail($id);

        $result = $this->bookingService->checkIn($booking->id, $request->all());
        $payload = json_decode((string) $result->getContent(), true);
        $ok = (bool) ($payload['status'] ?? false);

        return response()->json([
            'success' => $ok,
            'message' => $payload['message'] ?? ($ok ? 'Checked in.' : 'Check-in failed.'),
            'data' => $payload['data'] ?? null,
            'errors' => $payload['errors'] ?? null,
        ], $ok ? 200 : 422);
    }

    public function checkOut(Request $request, int $id): JsonResponse
    {
        $ownerId = $this->merchantOwnerId($request);
        $this->assertHotelAllowed($ownerId);

        $booking = Booking::query()
            ->hotel()
            ->with(['hotelItems.hotel'])
            ->whereHas('hotelItems.hotel', function ($hq) use ($ownerId) {
                $hq->where('merchant_id', $ownerId);
            })
            ->findOrFail($id);

        $checkoutDate = $request->input('checkout_date');
        $result = $this->bookingService->checkOut($booking->id, $checkoutDate);
        $payload = json_decode((string) $result->getContent(), true);
        $ok = (bool) ($payload['status'] ?? false);

        return response()->json([
            'success' => $ok,
            'message' => $payload['message'] ?? ($ok ? 'Checked out.' : 'Check-out failed.'),
            'data' => $payload['data'] ?? null,
        ], $ok ? 200 : 422);
    }

    private function transition(Request $request, int $id, string $action): JsonResponse
    {
        $ownerId = $this->merchantOwnerId($request);
        $this->assertHotelAllowed($ownerId);

        $booking = Booking::query()
            ->hotel()
            ->with(['hotelItems.hotel'])
            ->whereHas('hotelItems.hotel', function ($hq) use ($ownerId) {
                $hq->where('merchant_id', $ownerId);
            })
            ->findOrFail($id);

        $result = $this->bookingService->{$action}($booking->id);
        $payload = json_decode((string) $result->getContent(), true);
        $ok = (bool) ($payload['status'] ?? false);
        $fallback = $action === 'checkIn' ? 'Checked in.' : 'Done.';

        return response()->json([
            'success' => $ok,
            'message' => $payload['message'] ?? ($ok ? $fallback : 'Action failed.'),
            'data' => $payload['data'] ?? null,
        ], $ok ? 200 : 422);
    }

    /**
     * Expand merchant-desk room rows that only include hotel_room_id into the
     * hotel_id / room_type_id / rate_plan_id / room_id shape required by HotelBookingService.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function expandMerchantRoomRows(array $input, int $ownerId): array
    {
        $topHotelId = (int) ($input['hotel_id'] ?? 0);
        $rooms = $input['rooms'] ?? null;
        if (! is_array($rooms) || $rooms === []) {
            return $input;
        }

        $expanded = [];
        foreach ($rooms as $roomRow) {
            if (! is_array($roomRow)) {
                continue;
            }

            $quantity = max(1, (int) ($roomRow['quantity'] ?? 1));
            $hotelRoomId = (int) ($roomRow['room_id'] ?? $roomRow['hotel_room_id'] ?? 0);

            if ($hotelRoomId > 0) {
                $hotelRoom = HotelRoom::query()
                    ->where('id', $hotelRoomId)
                    ->whereHas('hotel', fn ($q) => $q->where('merchant_id', $ownerId))
                    ->first();

                if ($hotelRoom) {
                    $roomRow['room_id'] = $hotelRoom->id;
                    $roomRow['hotel_id'] = $roomRow['hotel_id'] ?? $hotelRoom->hotel_id;
                    $roomRow['room_type_id'] = $roomRow['room_type_id'] ?? $hotelRoom->room_type_id;

                    if (empty($roomRow['rate_plan_id']) && $hotelRoom->room_type_id) {
                        $ratePlanId = RoomRatePlan::query()
                            ->where('room_type_id', $hotelRoom->room_type_id)
                            ->where('status', 1)
                            ->orderBy('id')
                            ->value('id');
                        if ($ratePlanId) {
                            $roomRow['rate_plan_id'] = $ratePlanId;
                        }
                    }
                }
            } elseif ($topHotelId > 0 && empty($roomRow['hotel_id'])) {
                $roomRow['hotel_id'] = $topHotelId;
            }

            for ($i = 0; $i < $quantity; $i++) {
                $expanded[] = $roomRow;
            }
        }

        $input['rooms'] = $expanded;

        return $input;
    }
}

