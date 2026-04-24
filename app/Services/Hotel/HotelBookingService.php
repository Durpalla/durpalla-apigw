<?php

namespace App\Services\Hotel;

use App\Constants\AppConst;
use App\Models\Booking;
use App\Models\Hotel;
use App\Models\HotelHold;
use App\Models\HotelReservation;
use App\Models\HotelRoomType;
use App\Models\Payment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

final class HotelBookingService
{
    public function __construct(
        private readonly HotelPricingService $pricing,
        private readonly HotelInventoryService $inventory,
    ) {}

    public function search(Request $request): array
    {
        $debug = (bool) config('hotel.debug_search');
        $city = trim((string) $request->input('city', $request->input('trip_to', $request->input('trip_from', ''))));
        $checkIn = $this->parseDate($request->input('check_in', $request->input('trip_date')));
        $checkOut = $this->parseDate($request->input('check_out', $request->input('return_date')));

        if ($debug) {
            $this->emitHotelSearchDebug('request', [
                'endpoint' => 'GET /api/v1/hotel/search',
                'query' => $request->query(),
                'body' => $request->except(array_keys($request->query())),
                'resolved' => [
                    'city' => $city,
                    'check_in' => $checkIn?->toDateString(),
                    'check_out' => $checkOut?->toDateString(),
                ],
                'dates_ok' => $checkIn && $checkOut && $checkOut > $checkIn,
            ]);
        }

        if (! $checkIn || ! $checkOut || $checkOut <= $checkIn) {
            if ($debug) {
                $this->emitHotelSearchDebug('empty', [
                    'reason' => 'invalid_or_missing_dates',
                    'check_in_raw' => $request->input('check_in', $request->input('trip_date')),
                    'check_out_raw' => $request->input('check_out', $request->input('return_date')),
                ]);
            }

            return [];
        }

        $limit = max(1, min(100, (int) config('hotel.search_default_limit', 30)));
        $radiusKm = max(1.0, min(500.0, (float) config('hotel.search_radius_km', 50)));

        $q = Hotel::query()->where('hotels.status', 1);

        $lat = $request->input('lat');
        $lng = $request->input('lng');
        $hasGeo = is_numeric($lat) && is_numeric($lng);
        if ($hasGeo) {
            $latF = (float) $lat;
            $lngF = (float) $lng;
            if ($this->hotelsTableHasLatLng()) {
                $q->whereNotNull('hotels.lat')
                    ->whereNotNull('hotels.lng')
                    ->whereRaw(
                        '(6371 * acos(least(1.0, greatest(-1.0, cos(radians(?)) * cos(radians(hotels.lat)) * cos(radians(hotels.lng) - radians(?)) + sin(radians(?)) * sin(radians(hotels.lat)))))) <= ?',
                        [$latF, $lngF, $latF, $radiusKm]
                    );
            } elseif ($this->hotelLocationsHasCoordinates()) {
                $q->whereExists(function ($sub) use ($latF, $lngF, $radiusKm) {
                    $sub->selectRaw('1')
                        ->from('hotel_locations')
                        ->whereColumn('hotel_locations.hotel_id', 'hotels.id')
                        ->whereNotNull('hotel_locations.latitude')
                        ->whereNotNull('hotel_locations.longitude')
                        ->whereRaw(
                            '(6371 * acos(least(1.0, greatest(-1.0, cos(radians(?)) * cos(radians(hotel_locations.latitude)) * cos(radians(hotel_locations.longitude) - radians(?)) + sin(radians(?)) * sin(radians(hotel_locations.latitude)))))) <= ?',
                            [$latF, $lngF, $latF, $radiusKm]
                        );
                });
            }
        }
        if ($city !== '') {
            $like = '%'.addcslashes($city, '%_\\').'%';
            $q->where(function ($w) use ($like) {
                $w->where('hotels.name', 'like', $like);
                if ($this->hotelsTableHasCityString()) {
                    $w->orWhere('hotels.city', 'like', $like);
                }
                if ($this->hotelsTableHasCityId() && Schema::hasTable('cities')) {
                    $w->orWhereExists(function ($sub) use ($like) {
                        $sub->selectRaw('1')
                            ->from('cities')
                            ->whereColumn('cities.id', 'hotels.city_id')
                            ->where('cities.name', 'like', $like);
                    });
                }
            });
        }

        if ($debug) {
            $this->emitHotelSearchDebug('sql', [
                'sql' => $q->toSql(),
                'bindings' => $q->getBindings(),
            ]);
        }

        $hotels = $q->limit($limit)->get();
        $cityNamesById = [];
        if (! $this->hotelsTableHasCityString() && $this->hotelsTableHasCityId() && Schema::hasTable('cities')) {
            $ids = $hotels->pluck('city_id')->filter()->unique()->values()->all();
            if ($ids !== []) {
                foreach (DB::table('cities')->whereIn('id', $ids)->get(['id', 'name']) as $row) {
                    $cityNamesById[(int) $row->id] = (string) $row->name;
                }
            }
        }

        $out = [];
        $skippedNoPricedRoom = [];
        foreach ($hotels as $hotel) {
            $min = HotelRoomType::query()
                ->where('hotel_id', $hotel->id)
                ->where('status', 1)
                ->min('base_price_per_night');
            if ($min === null) {
                if ($debug) {
                    $skippedNoPricedRoom[] = [
                        'hotel_id' => $hotel->id,
                        'name' => $hotel->name,
                        'reason' => 'no_active_room_type_with_base_price_per_night',
                    ];
                }

                continue;
            }
            $photo = $hotel->photos()->first();
            $cityLabel = $this->resolveHotelCityLabel($hotel, $cityNamesById);
            $stars = $this->resolveHotelStars($hotel);
            $out[] = [
                'id' => $hotel->id,
                'hotel_id' => $hotel->id,
                'name' => $hotel->name,
                'location' => $cityLabel ?? $hotel->address ?? '',
                'city' => $cityLabel,
                'photo' => $photo?->url ?? '',
                'stars' => $stars,
                'rating' => (float) ($hotel->getAttribute('aggregate_rating') ?? $hotel->getAttribute('rating') ?? 0),
                'reviews' => (int) ($hotel->review_count ?? 0),
                'price_per_night' => (float) $min,
                'amenities' => [],
            ];
        }

        if ($debug) {
            $this->emitHotelSearchDebug('response', [
                'endpoint' => 'GET /api/v1/hotel/search',
                'candidate_hotel_count' => $hotels->count(),
                'candidate_ids' => $hotels->pluck('id')->values()->all(),
                'result_count' => count($out),
                'result_ids' => array_column($out, 'id'),
                'skipped' => $skippedNoPricedRoom,
                'schema' => [
                    'hotels_has_city_column' => $this->hotelsTableHasCityString(),
                    'hotels_has_city_id' => $this->hotelsTableHasCityId(),
                    'cities_table' => Schema::hasTable('cities'),
                ],
            ]);
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function emitHotelSearchDebug(string $phase, array $context): void
    {
        $context['phase'] = $phase;
        $line = '[hotel.search] '.$phase.' '.json_encode($context, JSON_UNESCAPED_UNICODE);
        error_log($line);
        Log::warning($line, $context);
    }

    public function hotelDetails(int $hotelId): ?array
    {
        $hotel = Hotel::query()->with(['photos', 'reviews', 'roomTypes.photos'])->find($hotelId);
        if (! $hotel || (int) $hotel->status !== 1) {
            return null;
        }

        $cityNamesById = [];
        $cid = $hotel->getAttribute('city_id');
        if (! $this->hotelsTableHasCityString() && $this->hotelsTableHasCityId() && Schema::hasTable('cities') && $cid !== null && $cid !== '') {
            $name = DB::table('cities')->where('id', $cid)->value('name');
            if ($name !== null) {
                $cityNamesById[(int) $cid] = (string) $name;
            }
        }

        return [
            'id' => $hotel->id,
            'name' => $hotel->name,
            'city' => $this->resolveHotelCityLabel($hotel, $cityNamesById),
            'address' => $hotel->address,
            'lat' => $hotel->lat ?? $this->firstHotelLocationLatitude($hotel->id),
            'lng' => $hotel->lng ?? $this->firstHotelLocationLongitude($hotel->id),
            'stars' => $this->resolveHotelStars($hotel),
            'rating' => (float) ($hotel->getAttribute('aggregate_rating') ?? $hotel->getAttribute('rating') ?? 0),
            'review_count' => (int) ($hotel->review_count ?? 0),
            'description' => $hotel->description,
            'policies' => $hotel->policies,
            'photos' => $hotel->photos->map(fn ($p) => ['url' => $p->url, 'caption' => $p->caption])->values()->all(),
            'reviews' => $hotel->reviews->map(fn ($r) => [
                'author' => $r->author,
                'rating' => (float) $r->rating,
                'text' => $r->body,
                'date' => $r->reviewed_at?->toDateString(),
            ])->values()->all(),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function roomsForStay(Request $request, int $hotelId): array
    {
        $hotel = Hotel::query()->find($hotelId);
        if (! $hotel) {
            return [];
        }
        $checkIn = $this->parseDate($request->input('check_in', $request->input('trip_date')));
        $checkOut = $this->parseDate($request->input('check_out', $request->input('return_date')));
        $adults = max(1, (int) $request->input('adults', 2));
        $children = max(0, (int) $request->input('children', 0));
        if (! $checkIn || ! $checkOut || $checkOut <= $checkIn) {
            return [];
        }

        $types = HotelRoomType::query()
            ->where('hotel_id', $hotelId)
            ->where('status', 1)
            ->with('photos')
            ->get();

        $out = [];
        foreach ($types as $rt) {
            if ($adults + $children > (int) $rt->max_occupancy) {
                continue;
            }
            try {
                $this->inventory->assertAvailability($rt, $checkIn, $checkOut, 1);
                $available = true;
            } catch (\Throwable) {
                $available = false;
            }
            $quote = $this->pricing->quoteStay($rt, $checkIn, $checkOut, $adults, $children);
            $out[] = [
                'id' => $rt->id,
                'room_type_id' => $rt->id,
                'code' => $rt->code,
                'title' => $rt->title,
                'max_occupancy' => (int) $rt->max_occupancy,
                'bed_type' => $rt->bed_type,
                'amenities' => $rt->amenities ?? [],
                'photos' => $rt->photos->map(fn ($p) => ['url' => $p->url])->values()->all(),
                'available' => $available,
                'quote' => $quote,
            ];
        }

        return $out;
    }

    public function quote(Request $request): array
    {
        $rt = HotelRoomType::query()->findOrFail((int) $request->input('room_type_id'));
        $checkIn = $this->parseDate($request->input('check_in'));
        $checkOut = $this->parseDate($request->input('check_out'));
        $adults = max(1, (int) $request->input('adults', 2));
        $children = max(0, (int) $request->input('children', 0));
        if (! $checkIn || ! $checkOut || $checkOut <= $checkIn) {
            throw new \InvalidArgumentException('Invalid dates');
        }
        $this->inventory->assertAvailability($rt, $checkIn, $checkOut, 1);

        return $this->pricing->quoteStay($rt, $checkIn, $checkOut, $adults, $children);
    }

    /**
     * @throws \Throwable
     */
    public function createHold(User $user, array $input, string $idempotencyKey): HotelHold
    {
        $existing = HotelHold::query()
            ->where('idempotency_key', $idempotencyKey)
            ->where('user_id', $user->id)
            ->first();
        if ($existing) {
            return $existing;
        }

        $roomType = HotelRoomType::query()->findOrFail((int) $input['room_type_id']);
        $checkIn = $this->parseDate($input['check_in'] ?? null);
        $checkOut = $this->parseDate($input['check_out'] ?? null);
        $adults = max(1, (int) ($input['adults'] ?? 2));
        $children = max(0, (int) ($input['children'] ?? 0));
        if (! $checkIn || ! $checkOut || $checkOut <= $checkIn) {
            throw new \InvalidArgumentException('Invalid dates');
        }

        $quote = $this->pricing->quoteStay($roomType, $checkIn, $checkOut, $adults, $children);
        $ttl = max(5, (int) config('hotel.hold_ttl_minutes', 15));

        return DB::transaction(function () use ($user, $roomType, $checkIn, $checkOut, $adults, $children, $idempotencyKey, $quote, $ttl) {
            $this->inventory->applyHold($roomType, $checkIn, $checkOut, 1);

            return HotelHold::create([
                'user_id' => $user->id,
                'hotel_room_type_id' => $roomType->id,
                'check_in' => $checkIn->toDateString(),
                'check_out' => $checkOut->toDateString(),
                'adults' => $adults,
                'children' => $children,
                'idempotency_key' => $idempotencyKey,
                'expires_at' => now()->addMinutes($ttl),
                'status' => HotelHold::STATUS_PENDING,
                'total_amount' => $quote['total'],
                'quote_json' => $quote,
            ]);
        });
    }

    public function releaseHold(User $user, int $holdId): bool
    {
        $hold = HotelHold::query()->where('id', $holdId)->where('user_id', $user->id)->first();
        if (! $hold || $hold->status !== HotelHold::STATUS_PENDING) {
            return false;
        }

        return DB::transaction(function () use ($hold) {
            $roomType = $hold->roomType;
            $checkIn = Carbon::parse($hold->check_in);
            $checkOut = Carbon::parse($hold->check_out);
            $this->inventory->releaseHold($roomType, $checkIn, $checkOut, 1);
            $hold->update(['status' => HotelHold::STATUS_CANCELLED]);

            return true;
        });
    }

    /**
     * @return array{reservation: HotelReservation, booking: Booking, payment: Payment}
     */
    public function confirmFromHold(User $user, int $holdId): array
    {
        $hold = HotelHold::query()
            ->where('id', $holdId)
            ->where('user_id', $user->id)
            ->firstOrFail();

        if ($hold->status !== HotelHold::STATUS_PENDING || now()->greaterThan($hold->expires_at)) {
            throw new \RuntimeException('Hold is not valid');
        }

        $existing = HotelReservation::query()->where('hotel_hold_id', $hold->id)->first();
        if ($existing) {
            return $this->loadBookingPayment($existing);
        }

        return DB::transaction(function () use ($user, $hold) {
            $roomType = $hold->roomType;
            $hotel = $roomType->hotel;
            $checkIn = Carbon::parse($hold->check_in);
            $checkOut = Carbon::parse($hold->check_out);
            $quote = $hold->quote_json ?? $this->pricing->quoteStay(
                $roomType,
                $checkIn,
                $checkOut,
                (int) $hold->adults,
                (int) $hold->children
            );
            $total = (float) $quote['total'];

            $booking = Booking::create([
                'booking_date' => date('Y-m-d'),
                'customer_id' => $user->id,
                'user_id' => $user->id,
                'total_amount' => $total,
                'total_discount' => 0,
                'total_payable' => $total,
                'vat_amount' => (float) getOption('vat_amount', 0),
                'vat_total' => (float) ($quote['vat_amount'] ?? 0),
                'charge_amount' => (float) ($quote['charge_percent'] ?? 0),
                'charge_total' => (float) ($quote['charge_amount'] ?? 0),
                'booking_party' => 'jolzan',
                'platform' => 'android',
                'status' => AppConst::BOOKING_PENDING,
            ]);

            $paymentWindow = max(1, (int) config('hotel.payment_window_minutes', 10));
            $reservation = HotelReservation::create([
                'user_id' => $user->id,
                'hotel_hold_id' => $hold->id,
                'hotel_id' => $hotel->id,
                'hotel_room_type_id' => $roomType->id,
                'booking_id' => $booking->id,
                'check_in' => $hold->check_in,
                'check_out' => $hold->check_out,
                'adults' => $hold->adults,
                'children' => $hold->children,
                'total_payable' => $total,
                'currency' => $quote['currency'] ?? 'BDT',
                'status' => HotelReservation::STATUS_PENDING_PAYMENT,
                'quote_json' => $quote,
                'payment_due_at' => now()->addMinutes($paymentWindow),
            ]);

            $hold->update(['status' => HotelHold::STATUS_CONSUMED]);

            $payment = Payment::create([
                'booking_id' => $booking->id,
                'transaction_id' => strtoupper(uniqid((string) $booking->id, false)),
                'customer_id' => $user->id,
                'status' => 'pending',
                'paid_amount' => $total,
                'dues' => 0,
                'store_amount' => 0,
            ]);

            return compact('reservation', 'booking', 'payment');
        });
    }

    /**
     * @return array{reservation: HotelReservation, booking: Booking, payment: Payment}
     */
    private function loadBookingPayment(HotelReservation $reservation): array
    {
        $booking = $reservation->booking;
        if (! $booking) {
            throw new \RuntimeException('Booking missing for reservation');
        }
        $payment = Payment::query()->where('booking_id', $booking->id)->orderByDesc('id')->first();
        if (! $payment) {
            throw new \RuntimeException('Payment missing for reservation');
        }

        return ['reservation' => $reservation, 'booking' => $booking, 'payment' => $payment];
    }

    public function expireStaleHolds(): int
    {
        $n = 0;
        $stale = HotelHold::query()
            ->where('status', HotelHold::STATUS_PENDING)
            ->where('expires_at', '<', now())
            ->get();

        foreach ($stale as $hold) {
            try {
                DB::transaction(function () use ($hold, &$n) {
                    $roomType = $hold->roomType;
                    $checkIn = Carbon::parse($hold->check_in);
                    $checkOut = Carbon::parse($hold->check_out);
                    $this->inventory->releaseHold($roomType, $checkIn, $checkOut, 1);
                    $hold->update(['status' => HotelHold::STATUS_EXPIRED]);
                    $n++;
                });
            } catch (\Throwable) {
            }
        }

        return $n;
    }

    public function failUnpaidReservations(): int
    {
        $n = 0;
        $rows = HotelReservation::query()
            ->where('status', HotelReservation::STATUS_PENDING_PAYMENT)
            ->whereNotNull('payment_due_at')
            ->where('payment_due_at', '<', now())
            ->get();

        foreach ($rows as $res) {
            try {
                DB::transaction(function () use ($res, &$n) {
                    $roomType = $res->roomType;
                    $checkIn = Carbon::parse($res->check_in);
                    $checkOut = Carbon::parse($res->check_out);
                    $this->inventory->releaseHold($roomType, $checkIn, $checkOut, 1);
                    $res->update(['status' => HotelReservation::STATUS_FAILED]);
                    if ($res->booking) {
                        $res->booking->update(['status' => AppConst::BOOKING_FAILED]);
                    }
                    Payment::query()->where('booking_id', $res->booking_id)->update(['status' => 'failed']);
                    $n++;
                });
            } catch (\Throwable) {
            }
        }

        return $n;
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

    private function hotelsTable(): string
    {
        return (new Hotel)->getTable();
    }

    private function hotelsTableHasCityString(): bool
    {
        return Schema::hasColumn($this->hotelsTable(), 'city');
    }

    private function hotelsTableHasCityId(): bool
    {
        return Schema::hasColumn($this->hotelsTable(), 'city_id');
    }

    private function hotelsTableHasLatLng(): bool
    {
        $t = $this->hotelsTable();

        return Schema::hasColumn($t, 'lat') && Schema::hasColumn($t, 'lng');
    }

    private function hotelLocationsHasCoordinates(): bool
    {
        return Schema::hasTable('hotel_locations')
            && Schema::hasColumn('hotel_locations', 'latitude')
            && Schema::hasColumn('hotel_locations', 'longitude');
    }

    /**
     * @param  array<int|string, mixed>  $cityNamesById
     */
    private function resolveHotelCityLabel(Hotel $hotel, array $cityNamesById): ?string
    {
        if ($this->hotelsTableHasCityString()) {
            $v = $hotel->getAttribute('city');

            return $v !== null && $v !== '' ? (string) $v : null;
        }
        $cid = $hotel->getAttribute('city_id');
        if ($cid !== null && $cid !== '' && isset($cityNamesById[(int) $cid])) {
            return (string) $cityNamesById[(int) $cid];
        }

        return null;
    }

    private function resolveHotelStars(Hotel $hotel): int
    {
        if (Schema::hasColumn($this->hotelsTable(), 'star_rating')) {
            return (int) $hotel->getAttribute('star_rating');
        }
        if (Schema::hasColumn($this->hotelsTable(), 'rating')) {
            return (int) round((float) $hotel->getAttribute('rating'));
        }

        return 0;
    }

    private function firstHotelLocationLatitude(int $hotelId): ?float
    {
        if (! $this->hotelLocationsHasCoordinates()) {
            return null;
        }
        $v = DB::table('hotel_locations')->where('hotel_id', $hotelId)->value('latitude');

        return $v !== null ? (float) $v : null;
    }

    private function firstHotelLocationLongitude(int $hotelId): ?float
    {
        if (! $this->hotelLocationsHasCoordinates()) {
            return null;
        }
        $v = DB::table('hotel_locations')->where('hotel_id', $hotelId)->value('longitude');

        return $v !== null ? (float) $v : null;
    }
}
