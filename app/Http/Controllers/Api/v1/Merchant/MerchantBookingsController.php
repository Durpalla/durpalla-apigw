<?php

namespace App\Http\Controllers\Api\v1\Merchant;

use App\Constants\AppConst;
use App\Events\MerchantBookingCancelledEvent;
use App\Events\SupervisorAppTripSeatEvent;
use App\Events\TripPublicCartItemEvent;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\Merchant;
use App\Models\MerchantStaff;
use App\Models\Payment;
use App\Models\PaymentCollector;
use App\Models\ScheduleCabinMapping;
use App\Services\BookingPnrService;
use App\Services\MerchantBookingPaymentService;
use App\Services\Transport\TransportSeatReferenceResolver;
use App\Support\ResolvesMerchantOwner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use App\Constants\GatewayConstant;
use App\Services\GatewayCatalogService;

/**
 * Merchant Desk Pro — booking lookup, list/history, detail, and cancel (merchant-scoped by trip ownership).
 */
class MerchantBookingsController extends Controller
{
    use ResolvesMerchantOwner;

    public function __construct(
        private readonly TransportSeatReferenceResolver $seatResolver,
        private readonly MerchantBookingPaymentService $merchantPayments,
        private readonly GatewayCatalogService $gatewayCatalog,
    ) {}

    /**
     * GET /merchant/bookings — paginated history for schedules owned by this merchant.
     */
    public function index(Request $request): JsonResponse
    {
        $ownerId = $this->merchantOwnerId($request);
        $perPage = min(50, max(5, (int) $request->query('per_page', 15)));

        $query = Booking::query()
            ->with([
                'payment',
                'customer',
                'bookingItems' => fn ($q) => $q->orderBy('id'),
                'bookingItems.trip.startFrom',
                'bookingItems.trip.stopTo',
            ])
            ->whereHas('bookingItems', function ($q) use ($ownerId) {
                $q->whereHas('trip', function ($tq) use ($ownerId) {
                    $tq->where('merchant_id', $ownerId);
                });
            });

        if ($request->filled('from')) {
            $query->whereDate('booking_date', '>=', date('Y-m-d', strtotime((string) $request->query('from'))));
        }
        if ($request->filled('to')) {
            $query->whereDate('booking_date', '<=', date('Y-m-d', strtotime((string) $request->query('to'))));
        }

        if ($request->filled('status')) {
            $st = strtoupper(trim((string) $request->query('status')));
            $allowed = [
                AppConst::BOOKING_COMPLETE,
                AppConst::BOOKING_RESERVED,
                AppConst::BOOKING_CANCELLED,
                AppConst::BOOKING_PENDING,
                AppConst::BOOKING_ADVANCE,
                AppConst::BOOKING_REJECTED,
                AppConst::BOOKING_FAILED,
            ];
            if (in_array($st, $allowed, true)) {
                $query->where('bookings.status', $st);
            }
        }

        if ($request->filled('q')) {
            $raw = trim((string) $request->query('q'));
            if ($raw !== '') {
                if (str_contains($raw, '@')) {
                    $raw = explode('@', $raw, 2)[0];
                }
                $pnrService = app(BookingPnrService::class);
                $pnr = $pnrService->normalize($raw);
                if ($pnr !== null) {
                    // Exact PNR / invoice number match.
                    $query->where('bookings.pnr', $pnr);
                } elseif (preg_match('/^BK-(\d+)$/i', $raw, $m)) {
                    $query->where('bookings.id', (int) $m[1]);
                } elseif (preg_match('/^D\d{6}-/i', $raw)) {
                    // Partial invoice/PNR entry (e.g. D260808-Z29…).
                    $query->where('bookings.pnr', 'like', strtoupper($raw).'%');
                } elseif (ctype_digit($raw)) {
                    $query->where(function ($q) use ($raw) {
                        $q->where('bookings.id', (int) $raw)
                            ->orWhere('bookings.pnr', 'like', '%'.$raw.'%');
                    });
                } else {
                    $like = '%'.$raw.'%';
                    $query->where(function ($q) use ($like, $raw) {
                        $q->where('bookings.pnr', 'like', strtoupper($raw).'%')
                            ->orWhere('bookings.pnr', 'like', $like)
                            ->orWhereHas('customer', function ($cq) use ($like) {
                                $cq->where('name', 'like', $like)
                                    ->orWhere('mobile', 'like', $like);
                            })
                            ->orWhereHas('bookingItems', function ($iq) use ($like) {
                                $iq->where('passenger', 'like', $like);
                            });
                    });
                }
            }
        }

        $paginator = $query->orderByDesc('id')->paginate($perPage);
        $rows = collect($paginator->items())->map(function (Booking $b) {
            $first = $b->bookingItems->first();
            $passengerName = $b->customer?->name ?? 'Guest';
            if ($first && $first->passenger) {
                $p = json_decode((string) $first->passenger, true);
                if (is_array($p) && ! empty($p['name'])) {
                    $passengerName = (string) $p['name'];
                }
            }
            $trip = $first?->trip;
            $tripSummary = '';
            if ($trip) {
                $tripSummary = ($trip->startFrom?->name ?? '').' → '.($trip->stopTo?->name ?? '');
            }

            $pay = $this->deskPaymentSnapshot($b);

            return [
                'bookingId' => $b->publicReference(),
                'status' => strtolower((string) $b->status),
                'totalFare' => (int) round((float) ($b->total_payable ?? $b->total_amount)),
                'amountPaid' => $pay['amountPaid'],
                'dueAmount' => $pay['dueAmount'],
                'bookingDate' => $b->booking_date ? date('Y-m-d', strtotime((string) $b->booking_date)) : null,
                'passengerName' => $passengerName,
                'ticketCount' => $b->bookingItems->count(),
                'tripSummary' => $tripSummary,
            ];
        })->values()->all();

        return response()->json([
            'success' => true,
            'data' => $rows,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    /**
     * GET /merchant/bookings/lookup?code=
     */
    public function lookup(Request $request): JsonResponse
    {
        $raw = $request->query('code', $request->input('code', ''));
        $code = is_string($raw) ? trim($raw) : trim((string) $raw);
        if ($code === '') {
            return response()->json(['message' => 'Code is required'], 422);
        }

        $slug = $this->resolveQrCodeToBookingSlug($code);
        if ($slug === null) {
            return response()->json(['message' => 'No booking found for this code'], 404);
        }

        $ownerId = $this->merchantOwnerId($request);
        $booking = $this->findMerchantScopedBooking($ownerId, $slug);
        if (! $booking) {
            return response()->json(['message' => 'Booking not found'], 404);
        }

        return response()->json($this->serializeBookingForDesk($booking));
    }

    /**
     * GET /merchant/bookings/{bookingSlug}
     */
    public function show(Request $request, string $bookingSlug): JsonResponse
    {
        $slug = $this->normalizeBookingSlug($bookingSlug);
        if ($slug === null) {
            return response()->json(['message' => 'Invalid booking id'], 422);
        }
        $ownerId = $this->merchantOwnerId($request);
        $booking = $this->findMerchantScopedBooking($ownerId, $slug);
        if (! $booking) {
            return response()->json(['message' => 'Booking not found'], 404);
        }

        return response()->json($this->serializeBookingForDesk($booking));
    }

    /**
     * POST /merchant/bookings/{bookingSlug}/collect — record cash/digital collection against due balance.
     * When the balance reaches zero, booking status becomes COMPLETE.
     */
    public function collect(Request $request, string $bookingSlug): JsonResponse
    {
        $slug = $this->normalizeBookingSlug($bookingSlug);
        if ($slug === null) {
            return response()->json(['message' => 'Invalid booking id'], 422);
        }
        $ownerId = $this->merchantOwnerId($request);
        $booking = $this->findMerchantScopedBooking($ownerId, $slug);
        if (! $booking) {
            return response()->json(['message' => 'Booking not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'amount' => ['required', 'numeric', 'min:1'],
            'method' => ['nullable', 'string', 'max:32'],
        ]);
        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()->first()], 422);
        }

        if (strtoupper((string) $booking->status) === AppConst::BOOKING_CANCELLED) {
            return response()->json(['message' => 'Cannot collect on a cancelled booking'], 422);
        }

        $total = (float) ($booking->total_payable ?? $booking->total_amount);
        $payment = $booking->payment;
        if (! $payment) {
            $payment = Payment::create([
                'booking_id' => $booking->id,
                'customer_id' => $booking->customer_id,
                'paid_amount' => 0,
                'dues' => max(0, $total),
                'payment_method' => 'pay_later',
                'payment_gateway' => 'pay_later',
                'status' => 'pending',
            ]);
            $booking->setRelation('payment', $payment);
        }

        $paidBefore = (float) ($payment->paid_amount ?? 0);
        $dueBefore = max(0.0, $total - $paidBefore);
        if ($dueBefore < 0.01) {
            return response()->json(['message' => 'No balance due'], 422);
        }

        $amount = min((float) $request->input('amount'), $dueBefore);
        if ($amount < 0.01) {
            return response()->json(['message' => 'Invalid amount'], 422);
        }

        $method = $this->normalizeCollectMethod($request->input('method'), (string) ($payment->payment_method ?? 'cash'));
        $uid = (int) $request->user()->id;
        $offlineCodes = $this->gatewayCatalog->listMerchantOfflineDesk()->pluck('code')->all();
        $channel = in_array($method, $offlineCodes, true)
            ? GatewayConstant::CHANNEL_OFFLINE
            : GatewayConstant::CHANNEL_MERCHANT;

        DB::transaction(function () use ($booking, $payment, $total, $amount, $method, $uid, $channel) {
            $newPaid = (float) $payment->paid_amount + $amount;
            $newDues = max(0.0, $total - $newPaid);
            $payment->paid_amount = $newPaid;
            $payment->dues = $newDues;
            $payment->payment_method = $method;
            $payment->payment_gateway = $method;
            $payment->channel = $channel;
            $payment->status = $newDues < 0.01 ? 'success' : 'pending';
            $payment->save();

            PaymentCollector::create([
                'booking_id' => $booking->id,
                'payment_id' => $payment->id,
                'supervisor_id' => $uid,
                'amount' => $amount,
                'payment_type' => $method,
            ]);

            $booking->payment_status = $newDues < 0.01 ? 1 : 0;
            $booking->status = $newDues < 0.01 ? AppConst::BOOKING_COMPLETE : AppConst::BOOKING_RESERVED;
            $booking->save();
        });

        $booking->refresh();
        $booking->load([
            'bookingItems.trip.vehicle',
            'bookingItems.trip.startFrom',
            'bookingItems.trip.startingPoint.ghat',
            'bookingItems.mapping.cabin.cabinType',
            'payment',
            'customer',
        ]);

        $snap = $this->deskPaymentSnapshot($booking);
        $message = $snap['dueAmount'] === 0 ? 'Payment complete — booking confirmed.' : 'Payment recorded.';

        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $this->serializeBookingForDesk($booking),
        ]);
    }

    /**
     * POST /merchant/bookings/{bookingSlug}/cancel
     */
    public function cancel(Request $request, string $bookingSlug): JsonResponse
    {
        $slug = $this->normalizeBookingSlug($bookingSlug);
        if ($slug === null) {
            return response()->json(['message' => 'Invalid booking id'], 422);
        }
        $ownerId = $this->merchantOwnerId($request);
        $booking = $this->findMerchantScopedBooking($ownerId, $slug);
        if (! $booking) {
            return response()->json(['message' => 'Booking not found'], 404);
        }

        if (strtoupper((string) $booking->status) === AppConst::BOOKING_CANCELLED) {
            return response()->json(['message' => 'Booking is already cancelled'], 422);
        }

        $booking->loadMissing(['bookingItems.mapping', 'bookingItems.trip', 'payment']);
        $itemIds = $booking->bookingItems->pluck('id')->map(fn ($id) => (int) $id)->values()->all();
        $bookingIdStr = $booking->publicReference();
        $uid = ($u = $request->user()) ? (int) $u->id : null;
        $wasPending = strtoupper((string) $booking->status) === AppConst::BOOKING_PENDING;

        DB::transaction(function () use ($booking, $wasPending) {
            if ($wasPending) {
                \App\Services\PendingBookingPaymentWindow::cancelPendingAndVoidPayment($booking);
                return;
            }
            $booking->status = AppConst::BOOKING_CANCELLED;
            $booking->save();
            $booking->bookingItems()->update(['status' => AppConst::BOOKING_ITEM_CANCELLED]);
            // Void unpaid (non-success) payment when cancelling.
            if ($booking->payment && $booking->payment->status !== AppConst::PAYMENT_SUCCESS) {
                $booking->payment->update(['status' => AppConst::PAYMENT_CANCELLED]);
            }
        });

        broadcast(new MerchantBookingCancelledEvent(
            (string) $ownerId,
            $bookingIdStr,
            $itemIds,
        ));

        $booking->refresh();
        $booking->loadMissing(['bookingItems.mapping', 'bookingItems.trip']);

        foreach ($booking->bookingItems as $item) {
            $trip = $item->trip;
            $mapping = $item->mapping;
            if ($trip && $mapping instanceof ScheduleCabinMapping) {
                TripPublicCartItemEvent::broadcastBookingItemCancelled(
                    (int) $trip->id,
                    $mapping,
                    (int) $item->id,
                    $uid,
                );
                $tripSlug = 'TRIP-'.str_pad((string) $trip->id, 3, '0', STR_PAD_LEFT);
                $seatId = $this->seatResolver->supervisorAppSeatId($mapping);
                SupervisorAppTripSeatEvent::broadcastSafely(
                    $tripSlug,
                    $seatId,
                    SupervisorAppTripSeatEvent::EVENT_CANCELLED,
                );
            }
        }

        return response()->json([
            'message' => 'Booking cancelled',
            'bookingId' => $bookingIdStr,
            'booking_item_ids' => $itemIds,
        ]);
    }

    private function normalizeBookingSlug(string $raw): ?string
    {
        $s = trim($raw);
        if ($s === '') {
            return null;
        }
        if (str_contains($s, '@')) {
            $s = explode('@', $s, 2)[0];
        }

        $pnr = app(BookingPnrService::class)->normalize($s);
        if ($pnr !== null) {
            return $pnr;
        }

        if (preg_match('/^BK-(\d+)$/i', $s, $m)) {
            return 'BK-'.str_pad($m[1], 5, '0', STR_PAD_LEFT);
        }

        if (ctype_digit($s)) {
            return 'BK-'.str_pad($s, 5, '0', STR_PAD_LEFT);
        }

        return null;
    }

    private function findMerchantScopedBooking(int $merchantOwnerId, string $bookingSlug): ?Booking
    {
        $q = Booking::with([
            'bookingItems.trip.vehicle',
            'bookingItems.trip.startFrom',
            'bookingItems.trip.stopTo',
            'bookingItems.trip.startingPoint.ghat',
            'bookingItems.trip.endingPoint.ghat',
            'bookingItems.mapping.cabin.cabinType',
            'payment',
            'customer',
            'bookedBy',
        ]);

        $pnrService = app(BookingPnrService::class);
        if ($pnrService->isValid($bookingSlug)) {
            $q->where('pnr', $pnrService->normalize($bookingSlug));
        } else {
            $id = (int) preg_replace('/^BK-/', '', $bookingSlug);
            if ($id <= 0) {
                return null;
            }
            $q->where('id', $id);
        }

        return $q
            ->whereHas('bookingItems', function ($q) use ($merchantOwnerId) {
                $q->whereHas('trip', function ($tq) use ($merchantOwnerId) {
                    $tq->where('merchant_id', $merchantOwnerId);
                });
            })
            ->first();
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeBookingForDesk(Booking $booking): array
    {
        $firstItem = $booking->bookingItems->first();
        $trip = $firstItem?->trip;
        $tripInfo = null;
        if ($trip) {
            $directionLine = $trip->tripDirectionRouteLine(' -> ');
            $tripInfo = [
                'tripId' => 'TRIP-'.str_pad((string) $trip->id, 3, '0', STR_PAD_LEFT),
                'routeName' => $directionLine !== '' ? $directionLine : ($firstItem->route_name ?? ''),
                'departureAt' => $trip->leaving_at ? date('c', strtotime((string) $trip->leaving_at)) : null,
                'tripDate' => $trip->schedule_date,
                'vehicleName' => $trip->vehicle?->name ?? '—',
                'terminalName' => $trip->startFrom?->name ?? $trip->startingPoint?->ghat?->name ?? '—',
            ];
        }

        $paymentInfo = $this->deskPaymentSnapshot($booking);

        $tickets = $booking->bookingItems->map(function ($i) use ($trip) {
            $passenger = is_string($i->passenger) ? json_decode($i->passenger, true) : (array) $i->passenger;
            $passengerName = $passenger['name'] ?? 'Guest';
            $passengerMobile = $passenger['mobile'] ?? null;
            $passengerCount = $passenger['person'] ?? 1;
            $passengerType = $passenger['ageGroup'] ?? $passenger['type'] ?? 'Adult';
            $seatOrCabin = '—';
            $seatCabinType = '—';
            $isAc = null;

            if ($i->booking_type === 'deck') {
                $seatOrCabin = 'Deck';
                $seatCabinType = 'Deck';
            } elseif ($i->mapping?->cabin) {
                $c = $i->mapping->cabin;
                $cabinType = $c->cabinType;
                $letter = $cabinType?->letter ?? '';
                $seatOrCabin = $letter ? $letter.'-'.($c->cabin_no ?? '') : ($c->cabin_no ?? '—');
                $seatCabinType = (string) ($cabinType?->name ?? $cabinType?->type ?? ucfirst((string) $i->booking_type));
                if ($cabinType !== null && $cabinType->is_ac !== null) {
                    $isAc = (bool) $cabinType->is_ac;
                }
            } else {
                $seatCabinType = ucfirst((string) ($i->booking_type ?: 'Seat'));
            }

            if ($isAc === null && $trip?->vehicle) {
                $isAc = (bool) ($trip->vehicle->ac_available ?? false);
            }

            return [
                'ticketId' => 'TK-'.str_pad((string) $i->id, 5, '0', STR_PAD_LEFT),
                'passengerName' => $passengerName,
                'passengerMobile' => $passengerMobile,
                'passengerCount' => (int) $passengerCount,
                'passengerType' => is_string($passengerType) ? ucfirst($passengerType) : 'Adult',
                'seatOrCabin' => $seatOrCabin,
                'seatCabinType' => $seatCabinType,
                'isAc' => $isAc,
                'acLabel' => $isAc === null ? '—' : ($isAc ? 'AC' : 'Non-AC'),
                'fare' => (int) round((float) $i->price),
                'type' => $i->booking_type,
                'route_name' => $i->route_name,
            ];
        })->values()->all();

        $ticketIds = $booking->bookingItems->map(fn ($i) => 'TK-'.str_pad((string) $i->id, 5, '0', STR_PAD_LEFT))->values()->all();

        return [
            'bookingId' => $booking->publicReference(),
            'ticketIds' => $ticketIds,
            'status' => strtolower((string) $booking->status),
            'totalFare' => (int) round((float) ($booking->total_payable ?? $booking->total_amount)),
            'bookingDate' => $booking->booking_date ? date('Y-m-d', strtotime((string) $booking->booking_date)) : null,
            'trip' => $tripInfo,
            'payment' => $paymentInfo,
            'bookingOfficer' => $this->deskBookingOfficer($booking),
            'tickets' => $tickets,
        ];
    }

    /**
     * @return array{name: string, role: string}|null
     */
    private function deskBookingOfficer(Booking $booking): ?array
    {
        $booking->loadMissing('bookedBy');
        $actor = $booking->bookedBy;

        if ($actor === null) {
            return null;
        }

        if ($actor instanceof MerchantStaff) {
            return [
                'name' => (string) $actor->name,
                'role' => (string) ($actor->type ?? ''),
                'type' => (string) ($actor->type ?? ''),
            ];
        }

        if ($actor instanceof Merchant) {
            return [
                'name' => (string) $actor->merchant_name,
                'role' => 'merchant',
            ];
        }

        return null;
    }

    /**
     * @return array{method: string, amountPaid: int, dueAmount: int, dues: int, paymentStatus: string, isFullyPaid: bool}
     */
    private function deskPaymentSnapshot(Booking $booking): array
    {
        $booking->loadMissing('payment');
        $total = (float) ($booking->total_payable ?? $booking->total_amount);
        $paid = (float) ($booking->payment?->paid_amount ?? 0);
        $due = max(0, (int) round($total - $paid));

        return [
            'method' => (string) ($booking->payment?->payment_method ?? 'pay_later'),
            'amountPaid' => (int) round($paid),
            'dueAmount' => $due,
            'dues' => $due,
            'paymentStatus' => (string) ($booking->payment?->status ?? 'pending'),
            'isFullyPaid' => $due === 0,
        ];
    }

    /**
     * POST /merchant/bookings/{slug}/payment-link
     */
    public function paymentLink(Request $request, string $bookingSlug): JsonResponse
    {
        $booking = $this->resolveScopedBookingOrFail($request, $bookingSlug);
        if ($booking instanceof JsonResponse) {
            return $booking;
        }

        $link = $this->merchantPayments->createPaymentLink($booking, true);

        return response()->json([
            'success' => true,
            'message' => __('Payment link created'),
            'data' => $link,
        ]);
    }

    /**
     * POST /merchant/bookings/{slug}/payment-qr
     */
    public function paymentQr(Request $request, string $bookingSlug): JsonResponse
    {
        $booking = $this->resolveScopedBookingOrFail($request, $bookingSlug);
        if ($booking instanceof JsonResponse) {
            return $booking;
        }

        $qr = $this->merchantPayments->createPaymentQr($booking);

        return response()->json([
            'success' => true,
            'message' => __('Payment QR created'),
            'data' => $qr,
        ]);
    }

    /**
     * POST /merchant/bookings/{slug}/attach-payment — officer attaches trx/ref after QR or manual pay.
     */
    public function attachPayment(Request $request, string $bookingSlug): JsonResponse
    {
        $booking = $this->resolveScopedBookingOrFail($request, $bookingSlug);
        if ($booking instanceof JsonResponse) {
            return $booking;
        }

        $validator = Validator::make($request->all(), [
            'amount' => ['required', 'numeric', 'min:1'],
            'trx_id' => ['required_without:external_reference', 'nullable', 'string', 'max:191'],
            'external_reference' => ['required_without:trx_id', 'nullable', 'string', 'max:191'],
            'method' => ['nullable', 'string', 'max:64'],
            'gateway_id' => ['nullable', 'integer'],
        ]);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => $validator->errors()->first()], 422);
        }

        $ref = (string) ($request->input('trx_id') ?: $request->input('external_reference'));
        $result = $this->merchantPayments->attachPayment(
            $booking,
            (float) $request->input('amount'),
            $ref,
            $request->input('method'),
            (int) $request->user()->id,
            $request->filled('gateway_id') ? (int) $request->input('gateway_id') : null,
        );

        if (! ($result['success'] ?? false)) {
            return response()->json($result, 422);
        }

        return response()->json([
            'success' => true,
            'message' => $result['message'],
            'data' => $this->serializeBookingForDesk($result['booking']),
        ]);
    }

    private function resolveScopedBookingOrFail(Request $request, string $bookingSlug): Booking|JsonResponse
    {
        $slug = $this->normalizeBookingSlug($bookingSlug);
        if ($slug === null) {
            return response()->json(['message' => 'Invalid booking id'], 422);
        }
        $ownerId = $this->merchantOwnerId($request);
        $booking = $this->findMerchantScopedBooking($ownerId, $slug);
        if (! $booking) {
            return response()->json(['message' => 'Booking not found'], 404);
        }

        return $booking;
    }

    private function normalizeCollectMethod(mixed $method, string $default): string
    {
        $m = is_string($method) ? strtolower(trim($method)) : '';
        $allowed = $this->gatewayCatalog->listMerchantOfflineDesk()
            ->pluck('code')
            ->merge(['cash', 'card', 'bkash', 'nagad', 'bank_check', 'bank_transfer'])
            ->unique()
            ->all();

        return in_array($m, $allowed, true) ? $m : (in_array($default, $allowed, true) ? $default : 'cash');
    }

    /**
     * @return non-empty-string|null Public PNR or legacy BK-##### slug
     */
    private function resolveQrCodeToBookingSlug(string $code): ?string
    {
        $code = trim($code);
        if ($code === '') {
            return null;
        }

        if (str_contains($code, '@')) {
            $code = explode('@', $code, 2)[0];
        }

        $pnrService = app(BookingPnrService::class);
        if ($pnrService->isValid($code)) {
            $booking = $pnrService->findBooking($code);

            return $booking?->publicReference();
        }

        if (preg_match('/D\d{6}-[A-Z]\d{5}-[A-Z]\d{5}/i', $code, $m)) {
            return $this->resolveQrCodeToBookingSlug($m[0]);
        }

        if (preg_match('/^BK-(\d+)$/i', $code, $m)) {
            $booking = Booking::query()->find((int) $m[1]);

            return $booking?->publicReference() ?? 'BK-'.str_pad($m[1], 5, '0', STR_PAD_LEFT);
        }

        if (preg_match('/^\d{1,12}$/', $code)) {
            $booking = Booking::query()->find((int) $code);

            return $booking?->publicReference() ?? 'BK-'.str_pad($code, 5, '0', STR_PAD_LEFT);
        }

        if (preg_match('/^TK-(\d+)$/i', $code, $m)) {
            $tid = (int) $m[1];
            $item = BookingItem::query()->with('booking')->find($tid);
            if (! $item) {
                return null;
            }

            return $item->booking?->publicReference()
                ?? 'BK-'.str_pad((string) $item->booking_id, 5, '0', STR_PAD_LEFT);
        }

        if (preg_match('/BK-\d+/i', $code, $m)) {
            return $this->resolveQrCodeToBookingSlug($m[0]);
        }

        if (preg_match('/TK-\d+/i', $code, $m)) {
            return $this->resolveQrCodeToBookingSlug($m[0]);
        }

        return null;
    }
}
