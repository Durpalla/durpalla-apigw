<?php

namespace App\Http\Controllers\Api\v1\Merchant;

use App\Constants\AppConst;
use App\Events\MerchantLiveBookingEvent;
use App\Events\TripPublicCartItemEvent;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\CabinLock;
use App\Models\Customer;
use App\Models\Merchant;
use App\Models\MerchantStaff;
use App\Models\ScheduleCabinMapping;
use App\Models\Payment;
use App\Models\PaymentCollector;
use App\Models\VehicleSchedule;
use App\Services\ApiIdempotencyService;
use App\Services\Transport\TransportSeatReferenceResolver;
use App\Support\AuthActor;
use App\Support\ResolvesMerchantOwner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Merchant Desk Pro — on-call / counter bookings for any vehicle schedule owned by the merchant.
 * Payment: full, partial, or none (pay later); mirrors supervisor app booking rules.
 */
class MerchantTripBookingController extends Controller
{
    use ResolvesMerchantOwner;

    public function __construct(
        private readonly TransportSeatReferenceResolver $seatResolver,
    ) {}

    private function assertMayBookOnBehalf(Merchant|MerchantStaff $user): void
    {
        if ($user instanceof Merchant) {
            return;
        }
        if ($user->hasAnyRole(['manager', 'counter', 'executive', 'ticket_master', 'supervisor'])) {
            return;
        }
        abort(403, 'Counter booking is not permitted for this account.');
    }

    private function getOwnedTrip(Request $request, int $tripId): VehicleSchedule
    {
        $ownerId = $this->merchantOwnerId($request);

        return VehicleSchedule::query()
            ->with(['vehicle.merchant', 'startFrom', 'stopTo', 'startingPoint.ghat', 'endingPoint.ghat'])
            ->where('id', $tripId)
            ->where('merchant_id', $ownerId)
            ->firstOrFail();
    }

    /**
     * @return list<array{cabin_id:int,mapping_id:int|null,fare:float}>
     */
    private function resolveItemsToCabinIds(VehicleSchedule $trip, string $ticketType, array $items): array
    {
        $resolved = [];
        foreach ($items as $raw) {
            $m = $this->seatResolver->resolveMapping($trip, (string) $raw);
            if ($m === null || strcasecmp((string) $m->type, $ticketType) !== 0) {
                continue;
            }
            $cabin = $m->cabin;
            $resolved[] = [
                'cabin_id' => $m->cabin_id,
                'mapping_id' => $m->id,
                'fare' => (float) ($m->fare ?? $cabin?->fare ?? 0),
                'supervisor_seat_id' => $this->seatResolver->supervisorAppSeatId($m),
            ];
        }

        return $resolved;
    }

    /**
     * Human-readable seat/cabin label for tickets and receipts (aligned with {@see MerchantTripLayoutController}).
     */
    private function merchantTicketSeatLabel(VehicleSchedule $trip, int $mappingId): string
    {
        if ($mappingId <= 0) {
            return 'Seat';
        }
        $m = ScheduleCabinMapping::query()
            ->where('schedule_id', $trip->id)
            ->where('id', $mappingId)
            ->with('cabin.cabinType')
            ->first();
        if ($m === null) {
            return 'Seat';
        }
        $trip->loadMissing('vehicle');
        $vehicleType = strtolower(trim((string) ($trip->vehicle?->vehicle_type ?? '')));
        $useLaunchStyleLabels = in_array($vehicleType, ['launch', 'boat'], true);
        $cabin = $m->cabin;
        if ($cabin === null) {
            return 'S'.$m->id;
        }
        if ($useLaunchStyleLabels) {
            return ($cabin->cabinType?->letter ? $cabin->cabinType->letter.'-' : '').$cabin->cabin_no;
        }
        $cabinNo = trim((string) ($cabin->cabin_no ?? ''));

        return $cabinNo !== ''
            ? $cabinNo
            : (($cabin->cabinType?->letter ? $cabin->cabinType->letter.'-' : '').$cabin->cabin_no);
    }

    /**
     * POST /merchant/trips/{tripId}/bookings
     *
     * Body (camelCase, same family as supervisor app):
     * - ticketType: seat|cabin|sofa
     * - items: string[] (labels / mapping ids understood by TransportSeatReferenceResolver)
     * - passengers: [{ name, mobile?, gender?, ageGroup? }, ...]
     * - payment.mode: full|partial|none
     * - payment.method: cash|card|bkash|nagad (ignored when mode=none → pay_later)
     * - payment.amountPaid: number (required for partial; ignored for full/none on server)
     */
    public function store(Request $request, int $tripId): JsonResponse
    {
        $actor = $request->user();
        if (! $actor instanceof Merchant && ! $actor instanceof MerchantStaff) {
            abort(401);
        }
        $this->assertMayBookOnBehalf($actor);

        $idempotency = app(ApiIdempotencyService::class);
        $idemKey = $idempotency->keyFromRequest();
        $actorId = (int) ($actor->id ?? 0);
        if ($idemKey !== '' && ! $idempotency->isValidKey($idemKey)) {
            return response()->json([
                'success' => false,
                'message' => __('Idempotency-Key must be 1–64 characters.'),
            ], 422);
        }
        if ($idemKey !== '' && $actorId > 0) {
            $cached = $idempotency->find('merchant_trip_booking', $actorId, $idemKey);
            if ($cached) {
                return $cached;
            }
        }

        // Total shutdown: inactive merchant cannot be booked from any channel.
        app(\Modules\Saas\App\Services\SaasEntitlementService::class)
            ->assertMerchantActive($this->merchantOwnerId($request));
        // OTA-only mode (overdue subscription): merchant desk / counter channel is cut.
        app(\Modules\Saas\App\Services\SaasEntitlementService::class)
            ->assertBookingChannelAllowed($this->merchantOwnerId($request), 'merchant');

        $validator = Validator::make($request->all(), [
            'ticketType' => ['required', Rule::in(['seat', 'cabin', 'sofa'])],
            'items' => ['required', 'array', 'min:1'],
            'items.*' => ['string'],
            'passengers' => ['required', 'array'],
            'passengers.*.name' => ['required', 'string'],
            'passengers.*.mobile' => ['nullable', 'string'],
            'passengers.*.gender' => ['nullable', 'string'],
            'passengers.*.ageGroup' => ['nullable', 'string'],
            'payment' => ['required', 'array'],
            'payment.mode' => ['required', Rule::in(['full', 'partial', 'none'])],
            'payment.method' => ['nullable', 'string', 'max:32'],
            'payment.amountPaid' => ['nullable', 'numeric', 'min:0', 'required_if:payment.mode,partial'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        $trip = $this->getOwnedTrip($request, $tripId);
        $ticketType = (string) $request->input('ticketType');
        $items = $request->input('items', []);

        $resolved = $this->resolveItemsToCabinIds($trip, $ticketType, $items);
        if (count($resolved) !== count($items)) {
            return response()->json([
                'success' => false,
                'message' => 'One or more seats are invalid or already booked',
            ], 422);
        }

        // CRITICAL: require locks for counter booking to prevent race conditions.
        // Merchant must lock each mapping first via /merchant/trips/{id}/seats/lock.
        $lockerToken = base64_encode((string) ($actor->email ?? ''));
        foreach ($resolved as $r) {
            $mid = (int) ($r['mapping_id'] ?? 0);
            if ($mid <= 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Lock required before booking.',
                ], 422);
            }
            $lock = CabinLock::query()
                ->where('mapping_id', $mid)
                ->where('trip_id', (int) $trip->id)
                ->where('customer_token', $lockerToken)
                ->first();
            if (! $lock) {
                return response()->json([
                    'success' => false,
                    'message' => 'Lock required before booking.',
                ], 422);
            }
        }

        $passengers = $request->input('passengers', []);
        $totalFare = 0.0;
        foreach ($resolved as $r) {
            $totalFare += (float) $r['fare'];
        }

        $mode = (string) $request->input('payment.mode', 'full');
        $methodIn = $request->input('payment.method');
        if ($mode === 'none') {
            $paid = 0.0;
            $method = 'pay_later';
        } elseif ($mode === 'full') {
            $paid = $totalFare;
            $method = $this->normalizeCollectMethod($methodIn, 'cash');
        } else {
            $paid = min(max(0.0, (float) $request->input('payment.amountPaid', 0)), $totalFare);
            if ($paid <= 0 || $paid >= $totalFare - 0.0001) {
                return response()->json([
                    'success' => false,
                    'message' => 'Partial payment must be greater than zero and less than total fare.',
                ], 422);
            }
            $method = $this->normalizeCollectMethod($methodIn, 'cash');
        }

        $customerMobile = $passengers[0]['mobile'] ?? null;
        $customerName = $passengers[0]['name'] ?? 'Guest';
        if (! $customerMobile) {
            return response()->json([
                'success' => false,
                'message' => 'Customer mobile is required',
            ], 422);
        }

        $customer = Customer::firstOrNew(['mobile' => $customerMobile]);
        if (! $customer->id) {
            $customer->name = $customerName;
            $customer->mobile = $customerMobile;
            $customer->password = Str::random(8);
            $customer->save();
        }

        $paymentStatus = $paid >= $totalFare - 0.0001 ? 'success' : 'pending';
        $fullyPaid = $paid >= $totalFare - 0.0001;
        $bookingStatus = $fullyPaid ? AppConst::BOOKING_COMPLETE : AppConst::BOOKING_RESERVED;

        DB::beginTransaction();
        try {
            $booking = Booking::create([
                'booking_date' => date('Y-m-d'),
                'customer_id' => $customer->id,
                'total_amount' => $totalFare,
                'total_discount' => 0,
                'vat_amount' => 0,
                'charge_amount' => 0,
                'total_payable' => $totalFare,
                'vat_total' => 0,
                'charge_total' => 0,
                'booking_party' => 'merchant',
                'status' => $bookingStatus,
                'payment_status' => $fullyPaid ? 1 : 0,
                'platform' => 'merchant_desk',
            ]);
            AuthActor::setBookedBy($booking, $actor);
            $booking->save();

            // starting_point / ending_point already reflect this run (straight vs reverse/down).
            $routeName = $trip->tripDirectionRouteLine(' -> ');

            $ticketIds = [];
            foreach ($resolved as $i => $r) {
                $passenger = $passengers[$i] ?? $passengers[0];
                $passengerJson = json_encode([
                    'name' => $passenger['name'] ?? $customerName,
                    'mobile' => $passenger['mobile'] ?? $customerMobile,
                    'gender' => $passenger['gender'] ?? null,
                    'ageGroup' => $passenger['ageGroup'] ?? 'adult',
                    'person' => 1,
                ]);
                $item = BookingItem::create([
                    'booking_id' => $booking->id,
                    'vehicle_id' => $trip->vehicle_id,
                    'customer_id' => $customer->id,
                    'booking_type' => $ticketType,
                    'cabin_id' => $r['cabin_id'],
                    'price' => $r['fare'],
                    'trip_id' => $trip->id,
                    'trip_date' => $trip->schedule_date,
                    'booking_date' => date('Y-m-d'),
                    'passenger' => $passengerJson,
                    'route_name' => $routeName,
                    'mapping_id' => $r['mapping_id'],
                    'status' => 1,
                ]);
                $ticketIds[] = 'TK-'.str_pad((string) $item->id, 5, '0', STR_PAD_LEFT);
            }

            // Clear locks now that booking is confirmed.
            $mappingIds = array_values(array_filter(array_map(fn ($r) => (int) ($r['mapping_id'] ?? 0), $resolved)));
            if ($mappingIds !== []) {
                CabinLock::query()
                    ->where('trip_id', (int) $trip->id)
                    ->whereIn('mapping_id', $mappingIds)
                    ->where('customer_token', $lockerToken)
                    ->delete();
                DB::table('schedule_cabin_mappings')
                    ->where('schedule_id', (int) $trip->id)
                    ->whereIn('id', $mappingIds)
                    ->update(['is_locked' => AppConst::BOOKING_ITEM_PENDING, 'lock_id' => null]);
            }

            $payment = Payment::create([
                'booking_id' => $booking->id,
                'customer_id' => $customer->id,
                'paid_amount' => $paid,
                'dues' => max(0, $totalFare - $paid),
                'payment_method' => $method,
                'payment_gateway' => $method,
                'status' => $paymentStatus,
            ]);

            if ($paid > 0.0001) {
                PaymentCollector::create([
                    'booking_id' => $booking->id,
                    'payment_id' => $payment->id,
                    'supervisor_id' => $request->user()->id,
                    'amount' => $paid,
                    'payment_type' => $method,
                ]);
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Booking failed: '.$e->getMessage(),
            ], 500);
        }

        $uid = (int) $request->user()->id;
        foreach ($resolved as $r) {
            $mapping = ScheduleCabinMapping::query()
                ->with(['cabinType', 'cabin.cabinType', 'schedule'])
                ->find((int) ($r['mapping_id'] ?? 0));
            if ($mapping) {
                TripPublicCartItemEvent::broadcastSafely(TripPublicCartItemEvent::EVENT_BOOKED, (int) $trip->id, $mapping, $uid);
            }
        }

        $bookingIdStr = $booking->publicReference();
        $booking->load('payment');
        MerchantLiveBookingEvent::dispatchFromBooking(
            $trip,
            $booking,
            count($items),
            $passengers[0]['name'] ?? $customerName,
            (float) $totalFare,
            $bookingIdStr,
        );
        $tickets = [];
        foreach ($ticketIds as $idx => $tkId) {
            $p = $passengers[$idx] ?? $passengers[0];
            $mid = (int) ($resolved[$idx]['mapping_id'] ?? 0);
            $tickets[] = [
                'ticketId' => $tkId,
                'passengerName' => $p['name'] ?? $customerName,
                'passengerMobile' => $p['mobile'] ?? $customerMobile,
                'seatOrCabin' => $this->merchantTicketSeatLabel($trip, $mid),
                'fare' => (int) round($resolved[$idx]['fare']),
                'type' => $ticketType,
            ];
        }
        $tripInfo = [
            'tripId' => 'TRIP-'.str_pad((string) $trip->id, 3, '0', STR_PAD_LEFT),
            'routeName' => $routeName,
            'departureAt' => $trip->leaving_at ? date('c', strtotime((string) $trip->leaving_at)) : null,
            'tripDate' => $trip->schedule_date,
            'vehicleName' => $trip->vehicle?->name ?? '—',
            'terminalName' => $trip->startFrom?->name ?? $trip->startingPoint?->ghat?->name ?? '—',
        ];

        $dueNow = (int) round(max(0, $totalFare - $paid));

        $body = [
            'success' => true,
            'message' => $fullyPaid ? 'Booking confirmed' : 'Booking reserved — collect due amount to complete.',
            'data' => [
                'bookingId' => $bookingIdStr,
                'ticketIds' => $ticketIds,
                'status' => strtolower($bookingStatus),
                'totalFare' => (int) round($totalFare),
                'bookingDate' => date('Y-m-d'),
                'trip' => $tripInfo,
                'payment' => [
                    'mode' => $mode,
                    'method' => $method,
                    'amountPaid' => (int) round($paid),
                    'dueAmount' => $dueNow,
                    'dues' => $dueNow,
                    'paymentStatus' => $paymentStatus,
                    'isFullyPaid' => $fullyPaid,
                ],
                'tickets' => $tickets,
            ],
        ];

        if ($idemKey !== '' && $actorId > 0) {
            $idempotency->remember(
                'merchant_trip_booking',
                $actorId,
                $idemKey,
                $body,
                201,
                (int) $booking->id
            );
        }

        return response()->json($body, 201);
    }

    private function normalizeCollectMethod(mixed $method, string $default): string
    {
        $m = is_string($method) ? strtolower(trim($method)) : '';
        $allowed = ['cash', 'card', 'bkash', 'nagad'];

        return in_array($m, $allowed, true) ? $m : $default;
    }
}
