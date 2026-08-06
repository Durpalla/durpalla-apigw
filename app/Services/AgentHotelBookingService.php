<?php

namespace App\Services;

use App\Constants\AppConst;
use App\Models\Agent;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\HotelHold;
use App\Models\HotelReservation;
use App\Models\HotelRoomType;
use App\Models\Payment;
use App\Services\Hotel\HotelInventoryService;
use App\Services\Hotel\HotelPricingService;
use App\Support\AuthActor;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class AgentHotelBookingService
{
    public function __construct(
        private readonly HotelInventoryService $inventory,
        private readonly HotelPricingService $pricing,
        private readonly AgentCounterPaymentService $counterPayments,
    ) {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function roomsForStay(Request $request, int $hotelId): array
    {
        if (! Schema::hasTable('hotels') || ! DB::table('hotels')->where('id', $hotelId)->exists()) {
            return [];
        }

        $this->syncModuleHotelRoomsIntoApiRoomTypes($hotelId);

        $checkIn = $this->parseDate($request->input('check_in', $request->input('trip_date')));
        $checkOut = $this->parseDate($request->input('check_out', $request->input('return_date')));
        $adults = max(1, (int) $request->input('adults', 2));
        $children = max(0, (int) $request->input('children', 0));
        if (! $checkIn || ! $checkOut || $checkOut <= $checkIn) {
            return [];
        }

        $t = (new HotelRoomType)->getTable();
        $typesQ = HotelRoomType::query()->where("{$t}.hotel_id", $hotelId);
        $this->applyRoomTypePublishScope($typesQ, $t);
        $types = $typesQ->with('photos')->get();

        $out = [];
        $relaxInv = (bool) config('hotel.rooms_treat_missing_inventory_as_available', true);
        foreach ($types as $rt) {
            if ($adults + $children > (int) $rt->max_occupancy) {
                continue;
            }
            try {
                $this->inventory->assertAvailability($rt, $checkIn, $checkOut, 1);
                $available = true;
            } catch (\Throwable) {
                $available = $relaxInv;
            }
            $quote = $this->pricing->quoteStay($rt, $checkIn, $checkOut, $adults, $children);
            $roomPhotos = [];
            foreach ($rt->photos as $p) {
                $u = $this->normalizeHotelImageUrl((string) ($p->url ?? ''));
                if ($u === '') {
                    continue;
                }
                $roomPhotos[] = ['url' => $u];
            }
            $out[] = [
                'id' => $rt->id,
                'room_type_id' => $rt->id,
                'code' => $rt->code,
                'title' => $rt->title,
                'max_occupancy' => (int) $rt->max_occupancy,
                'bed_type' => $rt->bed_type,
                'amenities' => $rt->amenities ?? [],
                'photos' => $roomPhotos,
                'available' => $available,
                'quote' => $quote,
            ];
        }

        return $out;
    }

    public function describeEmptyHotelRooms(Request $request, int $hotelId): ?string
    {
        if (! Schema::hasTable('hotels') || ! DB::table('hotels')->where('id', $hotelId)->exists()) {
            return 'Hotel not found.';
        }

        $this->syncModuleHotelRoomsIntoApiRoomTypes($hotelId);
        $checkIn = $this->parseDate($request->input('check_in', $request->input('trip_date')));
        $checkOut = $this->parseDate($request->input('check_out', $request->input('return_date')));
        $adults = max(1, (int) $request->input('adults', 2));
        $children = max(0, (int) $request->input('children', 0));
        $guests = $adults + $children;

        if (! $checkIn || ! $checkOut || $checkOut <= $checkIn) {
            return 'Valid check-in and check-out are required, and check-out must be after check-in.';
        }

        $t = (new HotelRoomType)->getTable();
        $typesQ = HotelRoomType::query()->where("{$t}.hotel_id", $hotelId);
        $this->applyRoomTypePublishScope($typesQ, $t);
        $types = $typesQ->get();

        if ($types->isEmpty()) {
            return 'This property has no bookable room types set up yet.';
        }

        $maxOfAny = (int) $types->max('max_occupancy');
        if ($guests > $maxOfAny) {
            return "No room type here fits {$guests} guests (largest max occupancy is {$maxOfAny}).";
        }

        return 'No rooms are available for this stay. Try different dates.';
    }

    public function createHold(Agent $agent, array $input, string $idempotencyKey): HotelHold
    {
        $existing = HotelHold::query()
            ->where('idempotency_key', $idempotencyKey)
            ->where('agent_id', $agent->id)
            ->first();
        if ($existing) {
            return $existing;
        }

        $checkIn = $this->parseDate($input['check_in'] ?? null);
        $checkOut = $this->parseDate($input['check_out'] ?? null);
        $adults = max(1, (int) ($input['adults'] ?? 2));
        $children = max(0, (int) ($input['children'] ?? 0));
        if (! $checkIn || ! $checkOut || $checkOut <= $checkIn) {
            throw new \InvalidArgumentException('Invalid dates');
        }

        $rawLines = $input['lines'] ?? null;
        if (! is_array($rawLines) || $rawLines === []) {
            $roomTypeId = (int) ($input['room_type_id'] ?? 0);
            if ($roomTypeId <= 0) {
                throw new \InvalidArgumentException('Provide lines or room_type_id');
            }
            $rawLines = [['room_type_id' => $roomTypeId, 'quantity' => 1]];
        }

        $mergedQty = [];
        foreach ($rawLines as $row) {
            if (! is_array($row)) {
                throw new \InvalidArgumentException('Invalid lines payload');
            }
            $rid = (int) ($row['room_type_id'] ?? 0);
            $qty = max(1, min(20, (int) ($row['quantity'] ?? 1)));
            if ($rid <= 0) {
                throw new \InvalidArgumentException('Invalid room_type_id in lines');
            }
            $mergedQty[$rid] = min(20, ($mergedQty[$rid] ?? 0) + $qty);
        }

        $hotelId = null;
        $resolved = [];
        foreach ($mergedQty as $roomTypeId => $quantity) {
            $rt = HotelRoomType::query()->findOrFail($roomTypeId);
            $hid = (int) $rt->hotel_id;
            if ($hotelId === null) {
                $hotelId = $hid;
            } elseif ($hid !== $hotelId) {
                throw new \InvalidArgumentException('All rooms must belong to the same hotel');
            }
            $this->syncModuleHotelRoomsIntoApiRoomTypes($hid);
            $resolved[] = ['room_type' => $rt->fresh() ?? $rt, 'quantity' => $quantity];
        }

        usort($resolved, fn (array $a, array $b): int => $a['room_type']->id <=> $b['room_type']->id);

        $lineOutputs = [];
        $grandTotal = 0.0;
        $sumVat = 0.0;
        $sumCharge = 0.0;
        $sumSub = 0.0;
        $currency = 'BDT';
        $nights = 0;
        foreach ($resolved as $entry) {
            /** @var HotelRoomType $rt */
            $rt = $entry['room_type'];
            $qty = $entry['quantity'];
            $q = $this->pricing->quoteStay($rt, $checkIn, $checkOut, $adults, $children);
            $unitTotal = (float) ($q['total'] ?? 0);
            $lineTotal = round($unitTotal * $qty, 2);
            $grandTotal += $lineTotal;
            $sumVat += round((float) ($q['vat_amount'] ?? 0) * $qty, 2);
            $sumCharge += round((float) ($q['charge_amount'] ?? 0) * $qty, 2);
            $sumSub += round((float) ($q['room_subtotal'] ?? 0) * $qty, 2);
            $currency = (string) ($q['currency'] ?? $currency);
            $nights = max($nights, (int) ($q['nights'] ?? 0));
            $lineOutputs[] = [
                'room_type_id' => $rt->id,
                'quantity' => $qty,
                'code' => $rt->code,
                'title' => $rt->title,
                'quote' => $q,
                'line_total' => $lineTotal,
            ];
        }

        $aggregateQuote = [
            'multi_room' => count($lineOutputs) > 1 || ($lineOutputs[0]['quantity'] ?? 1) > 1,
            'lines' => $lineOutputs,
            'total' => round($grandTotal, 2),
            'room_subtotal' => round($sumSub, 2),
            'vat_amount' => round($sumVat, 2),
            'charge_amount' => round($sumCharge, 2),
            'currency' => $currency,
            'nights' => $nights,
            'adults' => $adults,
            'children' => $children,
        ];

        $ttl = max(5, (int) config('hotel.hold_ttl_minutes', 15));
        /** @var HotelRoomType $primaryRoomType */
        $primaryRoomType = $resolved[0]['room_type'];

        return DB::transaction(function () use ($agent, $resolved, $checkIn, $checkOut, $adults, $children, $idempotencyKey, $aggregateQuote, $ttl, $primaryRoomType) {
            foreach ($resolved as $entry) {
                $this->inventory->applyHold($entry['room_type'], $checkIn, $checkOut, $entry['quantity']);
            }

            return HotelHold::create([
                'user_id' => null,
                'agent_id' => $agent->id,
                'hotel_room_type_id' => $primaryRoomType->id,
                'check_in' => $checkIn->toDateString(),
                'check_out' => $checkOut->toDateString(),
                'adults' => $adults,
                'children' => $children,
                'idempotency_key' => $idempotencyKey,
                'expires_at' => now()->addMinutes($ttl),
                'status' => HotelHold::STATUS_PENDING,
                'total_amount' => $aggregateQuote['total'],
                'quote_json' => $aggregateQuote,
            ]);
        });
    }

    /**
     * @return array{reservation: HotelReservation, booking: Booking, payment: Payment}
     */
    public function confirmForAgent(Agent $agent, int $holdId, array $input): array
    {
        $hold = HotelHold::query()
            ->where('id', $holdId)
            ->where('agent_id', $agent->id)
            ->firstOrFail();

        if ($hold->status !== HotelHold::STATUS_PENDING || now()->greaterThan($hold->expires_at)) {
            throw new \RuntimeException('Hold is not valid');
        }

        $existing = HotelReservation::query()->where('hotel_hold_id', $hold->id)->first();
        if ($existing && $existing->booking) {
            $payment = Payment::query()->where('booking_id', $existing->booking_id)->first();

            return [
                'reservation' => $existing,
                'booking' => $existing->booking,
                'payment' => $payment ?? Payment::query()->make(),
            ];
        }

        $customerMobile = trim((string) ($input['customer_mobile'] ?? ''));
        if ($agent->mobile && $customerMobile !== '' && $customerMobile === trim((string) $agent->mobile)) {
            throw new \InvalidArgumentException(
                "You cannot use your own mobile number. Please enter the customer's mobile number."
            );
        }

        $customer = $this->resolveCustomer(
            (string) ($input['customer_name'] ?? ''),
            $customerMobile
        );

        $method = $this->counterPayments->normalize(
            (string) ($input['payment_method'] ?? AgentCounterPaymentService::METHOD_FUND)
        );
        if ($method === 'cash') {
            throw new \InvalidArgumentException('Cash payment is not available for agent bookings.');
        }
        if (! in_array($method, $this->counterPayments->allowedCodes($agent), true)) {
            throw new \InvalidArgumentException('Invalid payment method');
        }

        $gatewayId = $input['gateway_id'] ?? null;
        $isFund = $this->counterPayments->isFund($method);
        $isLiveGateway = $this->counterPayments->isLiveGateway($method, $gatewayId);

        if (! $isFund && ! $isLiveGateway) {
            throw new \InvalidArgumentException('Use Fund or a Live payment gateway with gateway_id.');
        }
        if ($isLiveGateway && empty($gatewayId)) {
            throw new \InvalidArgumentException('gateway_id is required for digital payment');
        }

        return DB::transaction(function () use ($agent, $hold, $customer, $method, $input, $isFund, $isLiveGateway) {
            $roomType = $hold->roomType;
            $checkIn = Carbon::parse($hold->check_in)->startOfDay();
            $checkOut = Carbon::parse($hold->check_out)->startOfDay();
            $quote = is_array($hold->quote_json) ? $hold->quote_json : [];
            $total = (float) ($quote['total'] ?? $hold->total_amount);

            // Fund also starts PENDING; BookingCompletionService stamps COMPLETE after debit.
            $bookingStatus = ($isLiveGateway || $isFund)
                ? AppConst::BOOKING_PENDING
                : AppConst::BOOKING_COMPLETE;
            $reservationStatus = ($isLiveGateway || $isFund)
                ? HotelReservation::STATUS_PENDING_PAYMENT
                : HotelReservation::STATUS_CONFIRMED;

            $booking = new Booking([
                'booking_date' => date('Y-m-d'),
                'customer_id' => $customer->id,
                'total_amount' => $total,
                'total_discount' => 0,
                'total_payable' => $total,
                'vat_amount' => (float) getOption('vat_amount', 0),
                'vat_total' => (float) ($quote['vat_amount'] ?? 0),
                'charge_amount' => (float) ($quote['charge_percent'] ?? 0),
                'charge_total' => (float) ($quote['charge_amount'] ?? 0),
                'booking_party' => 'durpalla',
                'platform' => (string) ($input['platform'] ?? 'agent_app'),
                'status' => $bookingStatus,
                'service_type' => 'hotel',
                'from_date' => $checkIn->toDateString(),
                'to_date' => $checkOut->toDateString(),
            ]);
            AuthActor::setBookedBy($booking, $agent);
            $booking->save();

            $reservation = HotelReservation::create([
                'user_id' => $customer->id,
                'agent_id' => $agent->id,
                'hotel_hold_id' => $hold->id,
                'hotel_id' => (int) $roomType->hotel_id,
                'hotel_room_type_id' => $roomType->id,
                'booking_id' => $booking->id,
                'check_in' => $hold->check_in,
                'check_out' => $hold->check_out,
                'adults' => $hold->adults,
                'children' => $hold->children,
                'total_payable' => $total,
                'currency' => $quote['currency'] ?? 'BDT',
                'status' => $reservationStatus,
                'quote_json' => $quote,
                'payment_due_at' => $isLiveGateway ? now()->addHours(24) : null,
            ]);

            // Stamp after reservation exists — attribution resolves merchant via hotel_id.
            // Enables dual commission (booker + referrer) on live referred merchants.
            app(AgentReferralAttributionService::class)->attribute($booking);

            $hold->update([
                'status' => HotelHold::STATUS_CONSUMED,
                'user_id' => $customer->id,
            ]);

            $this->finalizeInventoryForStoredQuote(
                $quote,
                $checkIn,
                $checkOut,
                $roomType,
            );

            $paidAmount = $isFund
                ? $total
                : (isset($input['paid_amount']) ? (float) $input['paid_amount'] : $total);
            $dues = max(0, $total - $paidAmount);

            $payment = Payment::create([
                'booking_id' => $booking->id,
                'transaction_id' => strtoupper(uniqid((string) $booking->id, false)),
                'bank_tran_id' => $input['trx_id'] ?? null,
                'customer_id' => $customer->id,
                'gateway_id' => isset($input['gateway_id']) ? (int) $input['gateway_id'] : null,
                'payment_method' => $method,
                'channel' => $isLiveGateway ? 'live' : 'offline',
                'status' => ($isLiveGateway || $isFund) ? 'pending' : ($dues > 0 ? 'advance' : 'success'),
                'paid_amount' => ($isLiveGateway || $isFund) ? 0 : $paidAmount,
                'store_amount' => ($isLiveGateway || $isFund) ? 0 : $paidAmount,
                'dues' => ($isLiveGateway || $isFund) ? $total : $dues,
            ]);

            if ($isFund) {
                $payData = [];
                \App\Helpers\CommonHelper::purseGatewayByCode(AgentCounterPaymentService::METHOD_FUND)
                    ->create($payment, request(), $payData);
                if (empty($payData['success'])) {
                    throw new \InvalidArgumentException($payData['message'] ?? 'Fund payment failed');
                }
                $payment->refresh();
                $booking = app(BookingCompletionService::class)->complete($booking, $payment, [
                    'paid_amount' => $total,
                    'store_amount' => $total,
                    'dues' => 0,
                ]);
                $reservation->refresh();
            }

            return compact('reservation', 'booking', 'payment');
        });
    }

    public function formatHold(HotelHold $hold): array
    {
        return [
            'hold_id' => $hold->id,
            'expires_at' => $hold->expires_at?->toIso8601String(),
            'total' => (float) $hold->total_amount,
            'quote' => $hold->quote_json,
            'status' => $hold->status,
        ];
    }

    private function finalizeInventoryForStoredQuote(
        ?array $quoteJson,
        Carbon $checkIn,
        Carbon $checkOut,
        ?HotelRoomType $legacyRoomType,
    ): void {
        $lines = $this->inventoryLinesFromQuoteJson($quoteJson);
        if ($lines !== []) {
            foreach ($lines as $ln) {
                $rt = HotelRoomType::query()->find($ln['room_type_id']);
                if ($rt) {
                    $this->inventory->finalizeFromHold($rt, $checkIn, $checkOut, $ln['quantity']);
                }
            }

            return;
        }
        if ($legacyRoomType !== null) {
            $this->inventory->finalizeFromHold($legacyRoomType, $checkIn, $checkOut, 1);
        }
    }

    /**
     * @return list<array{room_type_id: int, quantity: int}>
     */
    private function inventoryLinesFromQuoteJson(?array $quoteJson): array
    {
        if (! is_array($quoteJson)) {
            return [];
        }
        $lines = $quoteJson['lines'] ?? null;
        if (! is_array($lines)) {
            return [];
        }
        $out = [];
        foreach ($lines as $line) {
            if (! is_array($line)) {
                continue;
            }
            // Multi-room aggregate uses room_type_id; single-room nested quote lines use date/rate only
            $rid = (int) ($line['room_type_id'] ?? 0);
            $qty = max(1, (int) ($line['quantity'] ?? 1));
            if ($rid > 0) {
                $out[] = ['room_type_id' => $rid, 'quantity' => $qty];
            }
        }

        return $out;
    }

    private function resolveCustomer(string $name, string $mobile): Customer
    {
        $mobile = trim($mobile);
        $name = trim($name);
        if ($mobile === '' || $name === '') {
            throw new \InvalidArgumentException('Customer name and mobile are required');
        }

        $customer = Customer::withTrashed()->where('mobile', $mobile)->first();
        if ($customer) {
            if (method_exists($customer, 'trashed') && $customer->trashed()) {
                $customer->restore();
            }
            if ($customer->name !== $name) {
                $customer->forceFill(['name' => $name])->save();
            }

            return $customer;
        }

        try {
            return Customer::query()->create([
                'name' => $name,
                'mobile' => $mobile,
                // Model casts password to hashed — pass raw value.
                'password' => Str::random(32),
                'status' => 1,
            ]);
        } catch (\Throwable $e) {
            report($e);
            throw new \InvalidArgumentException(
                'Unable to create customer: '.$e->getMessage()
            );
        }
    }

    private function syncModuleHotelRoomsIntoApiRoomTypes(int $hotelId): void
    {
        if (! Schema::hasTable('hotel_rooms') || ! Schema::hasTable('hotel_room_types')) {
            return;
        }

        $apiTable = (new HotelRoomType)->getTable();
        $q = $this->queryActiveModuleHotelRooms($hotelId, true);
        $rows = $q->orderBy('id')->get();
        if ($rows->isEmpty()) {
            $rows = $this->queryActiveModuleHotelRooms($hotelId, false)->orderBy('id')->get();
        }
        $activeHrIds = $rows->pluck('id')->map(fn ($id) => (int) $id)->all();

        $roomTypeNames = [];
        if (Schema::hasTable('room_types')) {
            $ids = $rows->pluck('room_type_id')->filter()->unique()->values()->all();
            if ($ids !== []) {
                $roomTypeNames = DB::table('room_types')->whereIn('id', $ids)->pluck('name', 'id')->all();
            }
        }

        foreach ($rows as $hr) {
            $code = 'mod_hr_'.$hr->id;
            $typeName = $roomTypeNames[$hr->room_type_id] ?? null;
            $name = trim((string) ($hr->name ?? ''));
            $title = $name !== '' ? $name : ($typeName !== null && (string) $typeName !== '' ? (string) $typeName : 'Room '.$hr->id);
            $baseFloat = ($hr->base_price !== null && $hr->base_price !== '') ? (float) $hr->base_price : 0.0;

            $payload = [
                'title' => $title,
                'max_occupancy' => max(1, (int) $hr->max_occupancy),
                'bed_type' => null,
                'amenities' => [],
                'base_price_per_night' => $baseFloat,
                'currency' => 'BDT',
                'status' => 1,
            ];
            if (Schema::hasColumn($apiTable, 'is_active')) {
                $payload['is_active'] = 1;
            }
            HotelRoomType::query()->updateOrCreate(
                [
                    'hotel_id' => (int) $hr->hotel_id,
                    'code' => $code,
                ],
                $payload
            );
        }

        if ($rows->isEmpty() || ! Schema::hasColumn($apiTable, 'status')) {
            return;
        }
        $prefix = 'mod_hr_';
        foreach (HotelRoomType::query()->where('hotel_id', $hotelId)->where('code', 'like', $prefix.'%')->get() as $rt) {
            $code = (string) $rt->code;
            if (! str_starts_with($code, $prefix)) {
                continue;
            }
            $suffix = substr($code, strlen($prefix));
            if ($suffix === '' || ! ctype_digit($suffix)) {
                continue;
            }
            $hrId = (int) $suffix;
            if (! in_array($hrId, $activeHrIds, true)) {
                $u = ['status' => 0];
                if (Schema::hasColumn($apiTable, 'is_active')) {
                    $u['is_active'] = 0;
                }
                $rt->update($u);
            }
        }
    }

    private function queryActiveModuleHotelRooms(int $hotelId, bool $strict)
    {
        $q = DB::table('hotel_rooms')->where('hotel_id', $hotelId);
        if (Schema::hasColumn('hotel_rooms', 'deleted_at')) {
            $q->whereNull('deleted_at');
        }
        if ($strict && Schema::hasColumn('hotel_rooms', 'status')) {
            $q->where(function ($w) {
                $w->whereNull('status')
                    ->orWhere('status', 1)
                    ->orWhere('status', '1')
                    ->orWhere('status', true)
                    ->orWhereIn('status', ['active', 'ACTIVE', 'enabled', 'ENABLED', 'published', 'PUBLISHED']);
            });
        }

        return $q;
    }

    private function applyRoomTypePublishScope($q, string $t): void
    {
        if (Schema::hasColumn($t, 'status')) {
            $q->where(function ($w) use ($t) {
                $w->whereNull("{$t}.status")
                    ->orWhere("{$t}.status", 1)
                    ->orWhere("{$t}.status", '1')
                    ->orWhereIn("{$t}.status", ['active', 'ACTIVE', 'published', 'PUBLISHED', 'enabled', 'ENABLED']);
            });
        }
        if (Schema::hasColumn($t, 'is_active')) {
            $q->where(function ($w) use ($t) {
                $w->whereNull("{$t}.is_active")
                    ->orWhere("{$t}.is_active", 1)
                    ->orWhere("{$t}.is_active", true);
            });
        }
    }

    private function normalizeHotelImageUrl(string $raw): string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return '';
        }
        if (preg_match('#^https?://#i', $raw) === 1) {
            return $raw;
        }
        $base = rtrim((string) config('hotel.image_public_base_url', ''), '/');
        if ($base === '') {
            $base = rtrim((string) config('app.url', ''), '/');
        }
        $path = str_replace('\\', '/', $raw);
        if (str_starts_with($path, '/')) {
            return $base.$path;
        }

        return $base.'/'.ltrim($path, '/');
    }

    private function parseDate(mixed $raw): ?Carbon
    {
        if ($raw === null || $raw === '') {
            return null;
        }
        try {
            return Carbon::parse($raw)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }
}
