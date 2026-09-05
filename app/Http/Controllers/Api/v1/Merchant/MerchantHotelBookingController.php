<?php

namespace App\Http\Controllers\Api\v1\Merchant;

use App\Models\Booking;
use App\Services\ApiIdempotencyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\Hotel;
use App\Models\HotelRoom;
use App\Models\RoomRatePlan;
use App\Http\Requests\Hotel\HotelBookingCreateRequest;
use App\Services\Hotel\MerchantDeskBookingService;
use Carbon\Carbon;

class MerchantHotelBookingController extends MerchantHotelBaseController
{
    private MerchantDeskBookingService $bookingService;

    public function __construct(MerchantDeskBookingService $bookingService)
    {
        $this->bookingService = $bookingService;
    }

    /**
     * POST /api/v1/merchant/hotel-bookings — walk-in / phone booking for merchant-owned hotels.
     *
     * Body: same shape as admin hotel booking create (see HotelBookingCreateRequest) plus:
     * - guest_name (required)
     * - guest_mobile (required) — stored on booking; does not create a customer account
     * - guest_email (optional)
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
        $rules['guest_email'] = ['nullable', 'email', 'max:191'];
        // Merchant desk may send hotel_room_id; we expand it before validating.
        $rules['hotel_id'] = ['nullable', 'integer', 'exists:hotels,id'];
        $rules['rooms.*.hotel_room_id'] = ['nullable', 'integer', 'exists:hotel_rooms,id'];
        $rules['rooms.*.quantity'] = ['nullable', 'integer', 'min:1', 'max:20'];
        $rules['payment.mode'] = ['nullable', 'string', 'in:full,partial,none'];
        $rules['payment.method'] = ['nullable', 'string', 'max:32'];
        $rules['payment.amountPaid'] = ['nullable', 'numeric', 'min:0', 'required_if:payment.mode,partial'];
        $rules['payment.amount_paid'] = ['nullable', 'numeric', 'min:0'];
        $rules['payment.transaction_id'] = ['nullable', 'string', 'max:128'];
        $rules['payment.account_no'] = ['nullable', 'string', 'max:64'];
        $rules['payment.remarks'] = ['nullable', 'string', 'max:500'];

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
        $guestEmail = trim((string) ($payload['guest_email'] ?? ''));
        unset($payload['guest_name'], $payload['guest_mobile'], $payload['guest_email'], $payload['hotel_id']);

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

        // Walk-in: keep guest contact on the booking only — do not create/link a Customer by mobile.
        $payload['customer_id'] = null;
        $payload['guest_name'] = $guestName;
        $payload['guest_mobile'] = $guestMobile;
        if ($guestEmail !== '') {
            $payload['guest_email'] = $guestEmail;
        }
        $payload['platform'] = 'merchant_desk';
        if (! empty($payload['payment']) && is_array($payload['payment'])) {
            // keep validated payment block
        } elseif ($request->filled('payment')) {
            $payload['payment'] = $request->input('payment');
        } else {
            $payload['payment'] = ['mode' => 'none'];
        }

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
            ->with(['hotelItems.hotel', 'hotelItems.roomType', 'hotelItems.ratePlan', 'customer', 'supplier', 'payment'])
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
                            ->orWhere('bookings.guest_name', 'like', $like)
                            ->orWhere('bookings.guest_mobile', 'like', $like)
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
            ->with([
                'hotelItems.hotel',
                'hotelItems.roomType',
                'hotelItems.ratePlan',
                'customer',
                'supplier',
                'payment',
                'collections.supervisor',
            ])
            ->whereHas('hotelItems.hotel', function ($hq) use ($ownerId) {
                $hq->where('merchant_id', $ownerId);
            })
            ->findOrFail($id);

        $data = $booking->toArray();
        $total = (float) ($booking->total_payable ?? $booking->total_amount ?? 0);
        $paid = (float) ($booking->payment?->paid_amount ?? 0);
        $due = max(0.0, round($total - $paid, 2));
        $data['payment_summary'] = [
            'method' => (string) ($booking->payment?->payment_method ?? 'pay_later'),
            'amount_paid' => $paid,
            'dues' => $due,
            'due_amount' => $due,
            'payment_status' => (string) ($booking->payment?->status ?? 'pending'),
            'is_fully_paid' => $due < 0.01,
        ];
        $data['collections'] = $booking->collections->map(function ($row) {
            return [
                'id' => (int) $row->id,
                'amount' => (float) $row->amount,
                'payment_type' => (string) $row->payment_type,
                'transaction_id' => (string) ($row->transaction_id ?? ''),
                'account_no' => (string) ($row->account_no ?? ''),
                'remarks' => (string) ($row->remarks ?? ''),
                'created_at' => optional($row->created_at)?->toIso8601String(),
                'collected_by' => $row->supervisor?->name,
            ];
        })->values()->all();

        return response()->json(['success' => true, 'data' => $data]);
    }

    /**
     * POST /api/v1/merchant/hotel-bookings/{id}/collect
     */
    public function collect(Request $request, int $id): JsonResponse
    {
        $ownerId = $this->merchantOwnerId($request);
        $this->assertHotelAllowed($ownerId);

        $validator = Validator::make($request->all(), [
            'amount' => ['required', 'numeric', 'min:0.01'],
            'method' => ['nullable', 'string', 'max:32'],
            'transaction_id' => ['nullable', 'string', 'max:128'],
            'account_no' => ['nullable', 'string', 'max:64'],
            'remarks' => ['nullable', 'string', 'max:500'],
        ]);
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        $booking = Booking::query()
            ->hotel()
            ->with(['hotelItems.hotel', 'payment'])
            ->whereHas('hotelItems.hotel', function ($hq) use ($ownerId) {
                $hq->where('merchant_id', $ownerId);
            })
            ->findOrFail($id);

        $result = $this->bookingService->collectPayment(
            $booking,
            (float) $request->input('amount'),
            $request->input('method'),
            (int) ($request->user()->id ?? 0),
            [
                'transaction_id' => $request->input('transaction_id'),
                'account_no' => $request->input('account_no'),
                'remarks' => $request->input('remarks'),
            ],
        );

        if (! ($result['success'] ?? false)) {
            return response()->json([
                'success' => false,
                'message' => (string) ($result['message'] ?? 'Collection failed'),
            ], 422);
        }

        $fresh = $result['booking'];
        $total = (float) ($fresh->total_payable ?? $fresh->total_amount ?? 0);
        $paid = (float) ($fresh->payment?->paid_amount ?? 0);
        $due = max(0.0, round($total - $paid, 2));

        return response()->json([
            'success' => true,
            'message' => (string) ($result['message'] ?? 'Payment recorded.'),
            'data' => $fresh,
            'payment_summary' => [
                'method' => (string) ($fresh->payment?->payment_method ?? 'cash'),
                'amount_paid' => $paid,
                'dues' => $due,
                'due_amount' => $due,
                'payment_status' => (string) ($fresh->payment?->status ?? 'pending'),
                'is_fully_paid' => $due < 0.01,
                'collected_amount' => (float) ($result['collected_amount'] ?? 0),
            ],
        ]);
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

    /**
     * POST /api/v1/merchant/hotel-bookings/{id}/extend
     * Body: new_check_out (Y-m-d), hotel_item_ids? (optional subset of rooms)
     */
    public function extend(Request $request, int $id): JsonResponse
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

        $validator = Validator::make($request->all(), [
            'new_check_out' => ['required', 'date'],
            'hotel_item_ids' => ['nullable', 'array'],
            'hotel_item_ids.*' => ['integer', 'min:1'],
        ]);
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors(),
            ], 422);
        }

        $result = $this->bookingService->extendStay($booking, $validator->validated());
        $payload = json_decode((string) $result->getContent(), true);
        $ok = (bool) ($payload['status'] ?? false);

        return response()->json([
            'success' => $ok,
            'message' => $payload['message'] ?? ($ok ? 'Stay extended.' : 'Extend failed.'),
            'data' => $payload['data'] ?? null,
        ], $ok ? 200 : 422);
    }

    /**
     * POST /api/v1/merchant/hotel-bookings/{id}/rooms
     * Body: rooms[] (same as walk-in), optional check_in_date / check_out_date (defaults to booking stay)
     */
    public function addRooms(Request $request, int $id): JsonResponse
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

        $input = $this->expandMerchantRoomRows($request->all(), $ownerId);
        $validator = Validator::make($input, [
            'check_in_date' => ['nullable', 'date'],
            'check_out_date' => ['nullable', 'date'],
            'adults' => ['nullable', 'integer', 'min:1'],
            'children' => ['nullable', 'integer', 'min:0'],
            'rooms' => ['required', 'array', 'min:1'],
            'rooms.*.hotel_id' => ['required', 'integer', 'exists:hotels,id'],
            'rooms.*.room_id' => ['required', 'integer', 'exists:hotel_rooms,id'],
            'rooms.*.room_type_id' => ['required', 'integer'],
            'rooms.*.rate_plan_id' => ['required', 'integer', 'exists:room_rate_plans,id'],
            'rooms.*.adults' => ['nullable', 'integer', 'min:1'],
            'rooms.*.children' => ['nullable', 'integer', 'min:0'],
            'rooms.*.children_ages' => ['nullable', 'array'],
        ]);
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();
        if (! empty($validated['check_in_date']) && ! empty($validated['check_out_date'])) {
            if (! Carbon::parse($validated['check_out_date'])->gt(Carbon::parse($validated['check_in_date']))) {
                return response()->json([
                    'success' => false,
                    'message' => 'Check-out date must be after check-in date.',
                ], 422);
            }
        }

        foreach ($validated['rooms'] as $roomRow) {
            $hotel = Hotel::query()->where('id', (int) $roomRow['hotel_id'])->where('merchant_id', $ownerId)->first();
            if (! $hotel) {
                return response()->json(['success' => false, 'message' => 'Hotel not owned by this merchant.'], 403);
            }
        }

        $result = $this->bookingService->addRooms($booking, $validated);
        $payload = json_decode((string) $result->getContent(), true);
        $ok = (bool) ($payload['status'] ?? false);

        return response()->json([
            'success' => $ok,
            'message' => $payload['message'] ?? ($ok ? 'Rooms added.' : 'Add rooms failed.'),
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

