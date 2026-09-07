<?php

namespace App\Services\Tour;

use App\Constants\AppConst;
use App\Models\Booking;
use App\Models\BookingTourItem;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\Tour;
use App\Models\TourDeparture;
use App\Models\TourHold;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class TourBookingService
{
    public function __construct(
        private readonly TourInventoryService $inventory,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function homeTop(Request $request): array
    {
        if (! Schema::hasTable('tours')) {
            return [];
        }

        $limit = max(1, min(20, (int) $request->query('limit', 8)));
        $tours = Tour::query()
            ->active()
            ->with([
                'images' => fn ($q) => $q->orderByRaw("CASE WHEN type = 'cover' THEN 0 ELSE 1 END")
                    ->orderBy('sort_order')
                    ->orderBy('id'),
                'city',
            ])
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        return $tours->map(fn (Tour $tour) => $this->cardPayload($tour))->values()->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function search(Request $request): array
    {
        if (! Schema::hasTable('tours')) {
            return [];
        }

        $limit = max(1, min(100, (int) $request->query('limit', config('tour.search_default_limit', 30))));
        $q = Tour::query()
            ->active()
            ->with([
                'images' => fn ($iq) => $iq->orderByRaw("CASE WHEN type = 'cover' THEN 0 ELSE 1 END")
                    ->orderBy('sort_order')
                    ->orderBy('id'),
                'city',
            ]);

        if ($request->filled('q') || $request->filled('search')) {
            $term = '%'.trim((string) ($request->input('q') ?: $request->input('search'))).'%';
            $q->where(function ($inner) use ($term) {
                $inner->where('title', 'LIKE', $term)
                    ->orWhere('destination', 'LIKE', $term)
                    ->orWhere('short_description', 'LIKE', $term);
            });
        }
        if ($request->filled('city_id')) {
            $q->where('city_id', (int) $request->input('city_id'));
        }
        if ($request->filled('destination')) {
            $q->where('destination', 'LIKE', '%'.trim((string) $request->input('destination')).'%');
        }
        if ($request->filled('depart_date') || $request->filled('from_date')) {
            $date = $this->parseDate($request->input('depart_date') ?: $request->input('from_date'));
            if ($date) {
                $q->whereHas('departures', function ($dq) use ($date) {
                    $dq->open()->whereDate('depart_date', '>=', $date->toDateString());
                });
            }
        }

        $tours = $q->orderByDesc('id')->limit($limit)->get();

        return $tours->map(fn (Tour $tour) => $this->cardPayload($tour))->values()->all();
    }

    public function show(int $tourId): ?array
    {
        if (! Schema::hasTable('tours')) {
            return null;
        }

        $tour = Tour::query()
            ->active()
            ->with([
                'images' => fn ($q) => $q->orderByRaw("CASE WHEN type = 'cover' THEN 0 ELSE 1 END")
                    ->orderBy('sort_order')
                    ->orderBy('id'),
                'itineraryDays',
                'city',
                'departures' => fn ($q) => $q->open()
                    ->whereDate('depart_date', '>=', now()->toDateString())
                    ->orderBy('depart_date'),
            ])
            ->find($tourId);

        if (! $tour) {
            return null;
        }

        $departures = $tour->departures->map(function (TourDeparture $d) {
            return [
                'id' => $d->id,
                'depart_date' => $d->depart_date?->toDateString(),
                'return_date' => $d->return_date?->toDateString(),
                'unit_price' => (float) $d->unit_price,
                'places_available' => $this->inventory->availablePlaces($d),
                'status' => $d->status,
            ];
        })->values()->all();

        return [
            'id' => $tour->id,
            'title' => $tour->title,
            'destination' => $tour->destination,
            'city_id' => $tour->city_id,
            'city_name' => $tour->city?->name,
            'duration_days' => (int) $tour->duration_days,
            'duration_nights' => (int) $tour->duration_nights,
            'short_description' => $tour->short_description,
            'long_description' => $tour->long_description,
            'meeting_point' => $tour->meeting_point,
            'min_people' => (int) $tour->min_people,
            'max_people' => $tour->max_people !== null ? (int) $tour->max_people : null,
            'cancellation_policy' => $tour->cancellation_policy,
            'images' => $tour->images->map(fn ($img) => [
                'id' => $img->id,
                'type' => $img->type,
                'url' => $img->image_url,
                'sort_order' => (int) $img->sort_order,
            ])->values()->all(),
            'itinerary' => $tour->itineraryDays->map(fn ($day) => [
                'day_number' => (int) $day->day_number,
                'title' => $day->title,
                'description' => $day->description,
            ])->values()->all(),
            'departures' => $departures,
            'from_price' => $departures !== []
                ? min(array_column($departures, 'unit_price'))
                : null,
        ];
    }

    public function quote(Request $request): array
    {
        $departure = TourDeparture::query()
            ->with('tour')
            ->findOrFail((int) $request->input('departure_id'));
        $places = max(1, (int) $request->input('places', 1));

        $this->assertPartySize($departure->tour, $places);
        $this->inventory->assertAvailability($departure, $places);

        $unit = (float) $departure->unit_price;
        $total = round($unit * $places, 2);

        return [
            'tour_id' => (int) $departure->tour_id,
            'departure_id' => (int) $departure->id,
            'tour_title' => $departure->tour?->title,
            'depart_date' => $departure->depart_date?->toDateString(),
            'return_date' => $departure->return_date?->toDateString(),
            'places' => $places,
            'unit_price' => $unit,
            'total' => $total,
            'currency' => 'BDT',
            'places_available' => $this->inventory->availablePlaces($departure),
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
    ): TourHold {
        $existingQ = TourHold::query()->where('idempotency_key', $idempotencyKey);
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

        $departure = TourDeparture::query()
            ->with('tour')
            ->findOrFail((int) ($input['departure_id'] ?? 0));
        if ($merchantId && (int) ($departure->tour?->merchant_id ?? 0) !== (int) $merchantId) {
            throw new \RuntimeException('Tour does not belong to this merchant.');
        }
        $places = max(1, (int) ($input['places'] ?? 1));
        $this->assertPartySize($departure->tour, $places);

        $unit = (float) $departure->unit_price;
        $total = round($unit * $places, 2);
        $ttl = max(5, (int) config('tour.hold_ttl_minutes', 15));
        $guest = is_array($input['guest'] ?? null) ? $input['guest'] : null;

        return DB::transaction(function () use ($user, $departure, $places, $unit, $total, $ttl, $idempotencyKey, $agentId, $merchantId, $guest) {
            $this->inventory->applyHold($departure, $places);

            return TourHold::create([
                'tour_id' => $departure->tour_id,
                'departure_id' => $departure->id,
                'user_id' => $user?->id,
                'merchant_id' => $merchantId,
                'agent_id' => $agentId,
                'places' => $places,
                'unit_price' => $unit,
                'total_price' => $total,
                'status' => TourHold::STATUS_PENDING,
                'expires_at' => now()->addMinutes($ttl),
                'guest_json' => $guest,
                'idempotency_key' => $idempotencyKey,
            ]);
        });
    }

    public function releaseHold(?Customer $user, int $holdId, ?int $agentId = null, ?int $merchantId = null): bool
    {
        $q = TourHold::query()
            ->where('id', $holdId)
            ->where('status', TourHold::STATUS_PENDING);
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

        DB::transaction(function () use ($hold) {
            $departure = TourDeparture::query()->find($hold->departure_id);
            if ($departure) {
                $this->inventory->releaseHold($departure, (int) $hold->places);
            }
            $hold->update(['status' => TourHold::STATUS_CANCELLED]);
        });

        return true;
    }

    /**
     * @param  array{mode?:string,method?:string,amount_paid?:float|int|string}|null  $payment
     * @return array{booking: Booking, payment: Payment, item: BookingTourItem}
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
        $q = TourHold::query()->where('id', $holdId);
        if ($user) {
            $q->where('user_id', $user->id);
        } elseif ($agentId) {
            $q->where('agent_id', $agentId);
        } elseif ($merchantId) {
            $q->where('merchant_id', $merchantId);
        }
        $hold = $q->firstOrFail();

        if ($hold->status === TourHold::STATUS_CONSUMED) {
            $item = BookingTourItem::query()
                ->where('tour_id', $hold->tour_id)
                ->where('departure_id', $hold->departure_id)
                ->where('places', $hold->places)
                ->whereHas('booking', function ($bq) use ($user, $merchantId) {
                    $bq->where('service_type', 'tour');
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

        if ($hold->status !== TourHold::STATUS_PENDING || ($hold->expires_at && now()->greaterThan($hold->expires_at))) {
            throw new \RuntimeException('Hold is not valid');
        }

        $guestPayload = [
            'name' => trim((string) ($guest['name'] ?? '')) ?: (string) ($user->name ?? ''),
            'mobile' => trim((string) ($guest['mobile'] ?? '')) ?: (string) ($user->mobile ?? ''),
            'email' => trim((string) ($guest['email'] ?? '')) ?: (string) ($user->email ?? ''),
        ];

        return DB::transaction(function () use ($user, $hold, $platform, $guestPayload, $paymentInput) {
            $departure = TourDeparture::query()->lockForUpdate()->findOrFail($hold->departure_id);
            $tour = Tour::query()->findOrFail($hold->tour_id);

            $this->inventory->consumeHold($departure, (int) $hold->places);

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
                'service_type' => 'tour',
                'from_date' => $departure->depart_date?->toDateString(),
                'to_date' => $departure->return_date?->toDateString() ?? $departure->depart_date?->toDateString(),
            ]);

            $item = BookingTourItem::create([
                'booking_id' => $booking->id,
                'tour_id' => $tour->id,
                'departure_id' => $departure->id,
                'places' => (int) $hold->places,
                'unit_price' => (float) $hold->unit_price,
                'total_price' => $total,
                'travelers' => $guestPayload,
                'tour_title' => $tour->title,
                'depart_date' => $departure->depart_date?->toDateString(),
                'destination' => $tour->destination,
            ]);

            $hold->update([
                'status' => TourHold::STATUS_CONSUMED,
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
                ->tour()
                ->with('tourItems.departure')
                ->lockForUpdate()
                ->findOrFail($bookingId);

            if (in_array(strtoupper((string) $booking->status), ['CANCELLED', 'FAILED'], true)) {
                return ['ok' => false, 'message' => 'Booking already cancelled.'];
            }

            foreach ($booking->tourItems as $item) {
                if ($item->departure) {
                    $this->inventory->revertSold($item->departure, (int) $item->places);
                }
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
    private function cardPayload(Tour $tour): array
    {
        $cover = $tour->images->first();
        $fromPrice = $tour->getAttribute('from_price');
        if ($fromPrice === null && Schema::hasTable('tour_departures')) {
            $fromPrice = TourDeparture::query()
                ->where('tour_id', $tour->id)
                ->open()
                ->whereDate('depart_date', '>=', now()->toDateString())
                ->min('unit_price');
        }

        return [
            'id' => $tour->id,
            'title' => $tour->title,
            'destination' => $tour->destination,
            'city_id' => $tour->city_id,
            'city_name' => $tour->city?->name,
            'duration_days' => (int) $tour->duration_days,
            'duration_nights' => (int) $tour->duration_nights,
            'short_description' => $tour->short_description,
            'thumbnail_url' => $cover?->image_url,
            'from_price' => $fromPrice !== null ? (float) $fromPrice : null,
        ];
    }

    private function assertPartySize(?Tour $tour, int $places): void
    {
        if (! $tour) {
            throw new \InvalidArgumentException('Tour not found');
        }
        if ($places < max(1, (int) $tour->min_people)) {
            throw new \InvalidArgumentException('Below minimum party size');
        }
        if ($tour->max_people !== null && $places > (int) $tour->max_people) {
            throw new \InvalidArgumentException('Exceeds maximum party size');
        }
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

    private function normalizePlatform(?string $platform): string
    {
        $p = strtolower(trim((string) $platform));
        $allowed = ['android', 'web', 'counter', 'office', 'agent_app', 'supervisor_app', 'merchant_desk'];

        return in_array($p, $allowed, true) ? $p : 'web';
    }
}
