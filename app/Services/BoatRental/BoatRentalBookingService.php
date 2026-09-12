<?php

namespace App\Services\BoatRental;

use App\Constants\AppConst;
use App\Models\Boat;
use App\Models\BoatHold;
use App\Models\BoatRate;
use App\Models\BoatTripPackage;
use App\Models\Booking;
use App\Models\BookingBoatItem;
use App\Models\Customer;
use App\Models\Payment;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class BoatRentalBookingService
{
    public function __construct(
        private readonly BoatInventoryService $inventory,
        private readonly BoatPricingService $pricing,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function homeTop(Request $request): array
    {
        if (! Schema::hasTable('boats')) {
            return [];
        }

        $limit = max(1, min(20, (int) $request->query('limit', 8)));
        $boats = Boat::query()
            ->active()
            ->with([
                'images' => fn ($q) => $q->orderByRaw("CASE WHEN type = 'cover' THEN 0 ELSE 1 END")
                    ->orderBy('sort_order')
                    ->orderBy('id'),
                'city',
                'rates',
                'tripPackages' => fn ($q) => $q->active(),
            ])
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        return $boats->map(fn (Boat $boat) => $this->cardPayload($boat))->values()->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function search(Request $request): array
    {
        if (! Schema::hasTable('boats')) {
            return [];
        }

        $limit = max(1, min(100, (int) $request->query('limit', config('boat_rental.search_default_limit', 30))));
        $q = Boat::query()
            ->active()
            ->with([
                'images' => fn ($iq) => $iq->orderByRaw("CASE WHEN type = 'cover' THEN 0 ELSE 1 END")
                    ->orderBy('sort_order')
                    ->orderBy('id'),
                'city',
                'rates',
                'tripPackages' => fn ($pq) => $pq->active(),
            ]);

        if ($request->filled('q') || $request->filled('search')) {
            $term = '%'.trim((string) ($request->input('q') ?: $request->input('search'))).'%';
            $q->where(function ($inner) use ($term) {
                $inner->where('title', 'LIKE', $term)
                    ->orWhere('base_location', 'LIKE', $term)
                    ->orWhere('short_description', 'LIKE', $term)
                    ->orWhereHas('city', fn ($cq) => $cq->where('name', 'LIKE', $term));
            });
        }
        if ($request->filled('city_id')) {
            $q->where('city_id', (int) $request->input('city_id'));
        }
        if ($request->filled('guests')) {
            $q->where('capacity_max', '>=', max(1, (int) $request->input('guests')));
        }
        if ($request->filled('rental_mode')) {
            $mode = strtolower(trim((string) $request->input('rental_mode')));
            if (in_array($mode, [BoatRate::MODE_HOURLY, BoatRate::MODE_DAILY], true)) {
                $q->whereHas('rates', fn ($rq) => $rq->where('rental_mode', $mode));
            } elseif ($mode === 'package') {
                $q->whereHas('tripPackages', fn ($pq) => $pq->active());
            }
        }

        $startsAt = $this->parseDateTime($request->input('starts_at'));
        $endsAt = $this->parseDateTime($request->input('ends_at'));
        if ($startsAt && $endsAt) {
            $q->where(function ($outer) use ($startsAt, $endsAt) {
                $outer->whereDoesntHave('holds', function ($hq) use ($startsAt, $endsAt) {
                    $hq->where('status', BoatHold::STATUS_PENDING)
                        ->where(function ($eq) {
                            $eq->whereNull('expires_at')->orWhere('expires_at', '>', now());
                        })
                        ->where('starts_at', '<', $endsAt)
                        ->where('ends_at', '>', $startsAt);
                });
            });
        }

        $boats = $q->orderByDesc('id')->limit($limit)->get();

        if ($startsAt && $endsAt) {
            $boats = $boats->filter(fn (Boat $boat) => $this->inventory->isAvailable((int) $boat->id, $startsAt, $endsAt));
        }

        return $boats->map(fn (Boat $boat) => $this->cardPayload($boat))->values()->all();
    }

    public function show(int $boatId): ?array
    {
        if (! Schema::hasTable('boats')) {
            return null;
        }

        $boat = Boat::query()
            ->active()
            ->with([
                'images' => fn ($q) => $q->orderByRaw("CASE WHEN type = 'cover' THEN 0 ELSE 1 END")
                    ->orderBy('sort_order')
                    ->orderBy('id'),
                'city',
                'rates',
                'tripPackages' => fn ($q) => $q->active()->with(['stops.stoppage']),
            ])
            ->find($boatId);

        if (! $boat) {
            return null;
        }

        $rates = $boat->rates->map(fn (BoatRate $rate) => [
            'id' => $rate->id,
            'rental_mode' => $rate->rental_mode,
            'unit_price' => (float) $rate->unit_price,
            'min_units' => (int) $rate->min_units,
            'max_units' => $rate->max_units !== null ? (int) $rate->max_units : null,
        ])->values()->all();

        $packages = $boat->tripPackages->map(function (BoatTripPackage $pkg) {
            return [
                'id' => $pkg->id,
                'title' => $pkg->title,
                'duration_hours' => (int) $pkg->duration_hours,
                'base_price' => (float) $pkg->base_price,
                'description' => $pkg->description,
                'stops' => $pkg->stops->map(fn ($stop) => [
                    'id' => $stop->id,
                    'stoppage_id' => (int) $stop->stoppage_id,
                    'sort_order' => (int) $stop->sort_order,
                    'name' => $stop->stoppage?->name,
                    'latitude' => $stop->stoppage?->latitude,
                    'longitude' => $stop->stoppage?->longitude,
                ])->values()->all(),
            ];
        })->values()->all();

        $priceCandidates = array_merge(
            array_column($rates, 'unit_price'),
            array_column($packages, 'base_price'),
        );

        return [
            'id' => $boat->id,
            'title' => $boat->title,
            'city_id' => $boat->city_id,
            'city_name' => $boat->city?->name,
            'capacity_max' => (int) $boat->capacity_max,
            'base_location' => $boat->base_location,
            'short_description' => $boat->short_description,
            'long_description' => $boat->long_description,
            'amenities' => $boat->amenities ?? [],
            'cancellation_policy' => $boat->cancellation_policy,
            'images' => $boat->images->map(fn ($img) => [
                'id' => $img->id,
                'type' => $img->type,
                'url' => $img->image_url,
                'sort_order' => (int) $img->sort_order,
            ])->values()->all(),
            'rates' => $rates,
            'packages' => $packages,
            'from_price' => $priceCandidates !== [] ? min($priceCandidates) : null,
        ];
    }

    public function quote(Request $request): array
    {
        $boat = Boat::query()->active()->findOrFail((int) $request->input('boat_id'));
        $mode = strtolower(trim((string) $request->input('rental_mode')));
        $guests = max(1, (int) $request->input('guests', 1));
        $startsAt = Carbon::parse((string) $request->input('starts_at'));
        $endsAt = Carbon::parse((string) $request->input('ends_at'));

        if ($endsAt->lessThanOrEqualTo($startsAt)) {
            throw new \InvalidArgumentException('ends_at must be after starts_at');
        }

        $this->inventory->assertCapacity((int) $boat->capacity_max, $guests);
        $this->inventory->assertAvailable((int) $boat->id, $startsAt, $endsAt);

        $priced = $this->resolvePrice($boat, $mode, $startsAt, $endsAt, $request->input('package_id'));

        return [
            'boat_id' => (int) $boat->id,
            'boat_title' => $boat->title,
            'rental_mode' => $mode,
            'package_id' => $priced['package_id'],
            'starts_at' => $startsAt->toIso8601String(),
            'ends_at' => $endsAt->toIso8601String(),
            'guests' => $guests,
            'units' => $priced['units'],
            'unit_price' => $priced['unit_price'],
            'total' => $priced['total'],
            'currency' => 'BDT',
        ];
    }

    /**
     * @throws \Throwable
     */
    public function createHold(
        ?Customer $user,
        array $input,
        string $idempotencyKey,
        ?int $agentId = null,
        ?int $merchantId = null,
    ): BoatHold {
        $existingQ = BoatHold::query()->where('idempotency_key', $idempotencyKey);
        if ($user) {
            $existingQ->where('user_id', $user->id);
        } elseif ($agentId) {
            $existingQ->where('agent_id', $agentId);
        } elseif ($merchantId) {
            $existingQ->where('merchant_id', $merchantId);
        }
        $existing = $existingQ->first();
        if ($existing) {
            return $existing;
        }

        $boat = Boat::query()->active()->findOrFail((int) ($input['boat_id'] ?? 0));
        if ($merchantId && (int) $boat->merchant_id !== (int) $merchantId) {
            throw new \RuntimeException('Boat does not belong to this merchant.');
        }

        $mode = strtolower(trim((string) ($input['rental_mode'] ?? '')));
        if (! in_array($mode, [BoatRate::MODE_HOURLY, BoatRate::MODE_DAILY, 'package'], true)) {
            throw new \InvalidArgumentException('rental_mode must be hourly, daily, or package');
        }

        $guests = max(1, (int) ($input['guests'] ?? 1));
        $startsAt = Carbon::parse((string) ($input['starts_at'] ?? ''));
        $endsAt = Carbon::parse((string) ($input['ends_at'] ?? ''));
        if ($endsAt->lessThanOrEqualTo($startsAt)) {
            throw new \InvalidArgumentException('ends_at must be after starts_at');
        }

        $this->inventory->assertCapacity((int) $boat->capacity_max, $guests);
        $priced = $this->resolvePrice($boat, $mode, $startsAt, $endsAt, $input['package_id'] ?? null);
        $ttl = max(5, (int) config('boat_rental.hold_ttl_minutes', 15));
        $guest = is_array($input['guest'] ?? null) ? $input['guest'] : null;
        $stoppages = $mode === 'package' && $priced['package']
            ? $this->resolveStoppagesJson($priced['package'])
            : null;

        return DB::transaction(function () use (
            $user, $boat, $mode, $guests, $startsAt, $endsAt, $priced, $ttl,
            $idempotencyKey, $agentId, $merchantId, $guest, $stoppages
        ) {
            $this->inventory->assertAvailable((int) $boat->id, $startsAt, $endsAt);

            return BoatHold::create([
                'boat_id' => $boat->id,
                'user_id' => $user?->id,
                'merchant_id' => $merchantId,
                'agent_id' => $agentId,
                'rental_mode' => $mode,
                'pricing_source' => $mode === 'package' ? 'package' : 'rate',
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'package_id' => $priced['package_id'],
                'guests' => $guests,
                'unit_price' => $priced['unit_price'],
                'total_price' => $priced['total'],
                'units' => $priced['units'],
                'stoppages_json' => $stoppages,
                'status' => BoatHold::STATUS_PENDING,
                'expires_at' => now()->addMinutes($ttl),
                'guest_json' => $guest,
                'idempotency_key' => $idempotencyKey,
            ]);
        });
    }

    public function releaseHold(?Customer $user, int $holdId, ?int $agentId = null, ?int $merchantId = null): bool
    {
        $q = BoatHold::query()
            ->where('id', $holdId)
            ->where('status', BoatHold::STATUS_PENDING);
        if ($user) {
            $q->where('user_id', $user->id);
        } elseif ($agentId) {
            $q->where('agent_id', $agentId);
        } elseif ($merchantId) {
            $q->where('merchant_id', $merchantId);
        }

        $hold = $q->first();
        if (! $hold) {
            return false;
        }

        $hold->update(['status' => BoatHold::STATUS_CANCELLED]);

        return true;
    }

    /**
     * @param  array{mode?:string,method?:string,amount_paid?:float|int|string}|null  $payment
     * @return array{booking: Booking, payment: Payment, item: BookingBoatItem}
     */
    public function confirmFromHold(
        ?Customer $user,
        int $holdId,
        ?string $platform = 'web',
        ?array $guest = null,
        ?int $agentId = null,
        ?int $merchantId = null,
        ?array $paymentInput = null,
    ): array {
        $q = BoatHold::query()->where('id', $holdId);
        if ($user) {
            $q->where('user_id', $user->id);
        } elseif ($agentId) {
            $q->where('agent_id', $agentId);
        } elseif ($merchantId) {
            $q->where('merchant_id', $merchantId);
        }
        $hold = $q->firstOrFail();

        if ($hold->status === BoatHold::STATUS_CONSUMED) {
            $item = BookingBoatItem::query()
                ->where('boat_id', $hold->boat_id)
                ->where('starts_at', $hold->starts_at)
                ->where('ends_at', $hold->ends_at)
                ->whereHas('booking', function ($bq) use ($user, $merchantId) {
                    $bq->where('service_type', 'boat_rental');
                    if ($user) {
                        $bq->where('customer_id', $user->id);
                    }
                    if ($merchantId) {
                        $bq->where('platform', 'merchant_desk');
                    }
                })
                ->latest('id')
                ->first();
            if ($item) {
                $booking = $item->booking;
                $payment = Payment::query()->where('booking_id', $booking->id)->orderByDesc('id')->firstOrFail();

                return ['booking' => $booking, 'payment' => $payment, 'item' => $item];
            }
        }

        if ($hold->status !== BoatHold::STATUS_PENDING || ($hold->expires_at && now()->greaterThan($hold->expires_at))) {
            throw new \RuntimeException('Hold is not valid');
        }

        $guestPayload = [
            'name' => trim((string) ($guest['name'] ?? '')) ?: (string) ($user->name ?? ''),
            'mobile' => trim((string) ($guest['mobile'] ?? '')) ?: (string) ($user->mobile ?? ''),
            'email' => trim((string) ($guest['email'] ?? '')) ?: (string) ($user->email ?? ''),
        ];

        return DB::transaction(function () use ($user, $hold, $platform, $guestPayload, $paymentInput) {
            $boat = Boat::query()->lockForUpdate()->findOrFail($hold->boat_id);
            $this->inventory->assertAvailable(
                (int) $boat->id,
                Carbon::parse($hold->starts_at),
                Carbon::parse($hold->ends_at),
                (int) $hold->id,
            );

            $total = (float) $hold->total_price;
            $bookingPlatform = $this->normalizePlatform($platform);
            $payMode = strtolower(trim((string) ($paymentInput['mode'] ?? 'none')));
            $amountPaid = (float) ($paymentInput['amount_paid'] ?? $paymentInput['amountPaid'] ?? 0);
            if ($payMode === 'full') {
                $amountPaid = $total;
            }
            if ($payMode === 'none') {
                $amountPaid = 0;
            }
            $amountPaid = max(0, min($total, round($amountPaid, 2)));
            $isPaid = $amountPaid + 0.001 >= $total;
            $bookingStatus = $isPaid ? AppConst::BOOKING_COMPLETE : AppConst::BOOKING_PENDING;

            $booking = Booking::create([
                'booking_date' => date('Y-m-d'),
                'customer_id' => $user?->id,
                'user_id' => $user?->id,
                'total_amount' => $total,
                'total_discount' => 0,
                'total_payable' => $total,
                'vat_amount' => (float) (function_exists('getOption') ? getOption('vat_amount', 0) : 0),
                'vat_total' => 0,
                'charge_amount' => 0,
                'charge_total' => 0,
                'booking_party' => 'durpalla',
                'platform' => $bookingPlatform,
                'status' => $bookingStatus,
                'service_type' => 'boat_rental',
                'from_date' => Carbon::parse($hold->starts_at)->toDateString(),
                'to_date' => Carbon::parse($hold->ends_at)->toDateString(),
            ]);

            $item = BookingBoatItem::create([
                'booking_id' => $booking->id,
                'boat_id' => $boat->id,
                'rental_mode' => $hold->rental_mode,
                'pricing_source' => $hold->pricing_source,
                'starts_at' => $hold->starts_at,
                'ends_at' => $hold->ends_at,
                'package_id' => $hold->package_id,
                'trip_request_id' => $hold->trip_request_id,
                'bid_id' => $hold->bid_id,
                'guests' => (int) $hold->guests,
                'units' => (int) $hold->units,
                'unit_price' => (float) $hold->unit_price,
                'total_price' => $total,
                'stoppages_json' => $hold->stoppages_json,
                'boat_title' => $boat->title,
                'travelers' => $guestPayload,
            ]);

            $hold->update([
                'status' => BoatHold::STATUS_CONSUMED,
                'guest_json' => $guestPayload,
            ]);

            $payment = Payment::create([
                'booking_id' => $booking->id,
                'transaction_id' => strtoupper(uniqid((string) $booking->id, false)),
                'customer_id' => $user?->id,
                'status' => $isPaid ? 'success' : ($amountPaid > 0 ? 'advance' : 'pending'),
                'payment_method' => $paymentInput['method'] ?? ($isPaid ? 'cash' : null),
                'paid_amount' => $amountPaid > 0 ? $amountPaid : $total,
                'dues' => max(0, round($total - $amountPaid, 2)),
                'store_amount' => $amountPaid,
            ]);

            return compact('booking', 'payment', 'item');
        });
    }

    public function cancelBooking(int $bookingId, ?string $reason = null): array
    {
        return DB::transaction(function () use ($bookingId, $reason) {
            $booking = Booking::query()
                ->boatRental()
                ->with('boatItems')
                ->lockForUpdate()
                ->findOrFail($bookingId);

            if (in_array(strtoupper((string) $booking->status), ['CANCELLED', 'FAILED'], true)) {
                return ['ok' => false, 'message' => 'Booking already cancelled.'];
            }

            $booking->update(['status' => AppConst::BOOKING_CANCELLED]);
            Payment::query()
                ->where('booking_id', $booking->id)
                ->whereNotIn('status', ['success', 'paid', 'complete', 'completed', 'advance'])
                ->update(['status' => 'fail']);

            return [
                'ok' => true,
                'message' => 'Cancelled.',
                'data' => ['booking_id' => $booking->id, 'reason' => $reason],
            ];
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function holdPayload(BoatHold $hold): array
    {
        return [
            'hold_id' => $hold->id,
            'boat_id' => (int) $hold->boat_id,
            'rental_mode' => $hold->rental_mode,
            'pricing_source' => $hold->pricing_source,
            'starts_at' => $hold->starts_at?->toIso8601String(),
            'ends_at' => $hold->ends_at?->toIso8601String(),
            'package_id' => $hold->package_id,
            'trip_request_id' => $hold->trip_request_id,
            'bid_id' => $hold->bid_id,
            'guests' => (int) $hold->guests,
            'units' => (int) $hold->units,
            'unit_price' => (float) $hold->unit_price,
            'total' => (float) $hold->total_price,
            'stoppages' => $hold->stoppages_json,
            'expires_at' => $hold->expires_at?->toIso8601String(),
            'status' => $hold->status,
        ];
    }

    /**
     * @return list<array<string, mixed>>|null
     */
    public function resolveStoppagesJson(BoatTripPackage $package): ?array
    {
        $package->loadMissing('stops.stoppage');
        $stops = $package->stops->map(fn ($stop) => [
            'stoppage_id' => (int) $stop->stoppage_id,
            'name' => $stop->stoppage?->name,
            'sort_order' => (int) $stop->sort_order,
            'latitude' => $stop->stoppage?->latitude,
            'longitude' => $stop->stoppage?->longitude,
        ])->values()->all();

        return $stops !== [] ? $stops : null;
    }

    /**
     * @return array{units:int,unit_price:float,total:float,package_id:?int,package:?BoatTripPackage}
     */
    private function resolvePrice(Boat $boat, string $mode, Carbon $startsAt, Carbon $endsAt, mixed $packageId): array
    {
        if ($mode === 'package') {
            $package = BoatTripPackage::query()
                ->active()
                ->where('boat_id', $boat->id)
                ->findOrFail((int) $packageId);
            $priced = $this->pricing->pricePackage($package);

            return $priced + ['package_id' => $package->id, 'package' => $package];
        }

        $rate = BoatRate::query()
            ->where('boat_id', $boat->id)
            ->where('rental_mode', $mode)
            ->firstOrFail();

        $priced = $mode === BoatRate::MODE_DAILY
            ? $this->pricing->priceDaily($rate, $startsAt, $endsAt)
            : $this->pricing->priceHourly($rate, $startsAt, $endsAt);

        return $priced + ['package_id' => null, 'package' => null];
    }

    /**
     * @return array<string, mixed>
     */
    private function cardPayload(Boat $boat): array
    {
        $cover = $boat->images->first();
        $prices = [];
        foreach ($boat->rates ?? [] as $rate) {
            $prices[] = (float) $rate->unit_price;
        }
        foreach ($boat->tripPackages ?? [] as $pkg) {
            $prices[] = (float) $pkg->base_price;
        }

        return [
            'id' => $boat->id,
            'title' => $boat->title,
            'city_id' => $boat->city_id,
            'city_name' => $boat->city?->name,
            'capacity_max' => (int) $boat->capacity_max,
            'base_location' => $boat->base_location,
            'short_description' => $boat->short_description,
            'thumbnail_url' => $cover?->image_url,
            'from_price' => $prices !== [] ? min($prices) : null,
        ];
    }

    private function parseDateTime(mixed $raw): ?Carbon
    {
        if ($raw === null || $raw === '') {
            return null;
        }
        try {
            return Carbon::parse($raw);
        } catch (\Throwable) {
            return null;
        }
    }

    private function normalizePlatform(?string $platform): string
    {
        $p = strtolower(trim((string) $platform));
        $allowed = ['android', 'web', 'counter', 'office', 'agent_app', 'supervisor_app', 'merchant_desk'];

        return in_array($p, $allowed, true) ? $p : 'web';
    }
}
