<?php

namespace App\Services\Hotel;

use App\Constants\AppConst;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\Hotel;
use App\Models\HotelHold;
use App\Models\HotelReservation;
use App\Models\HotelRoomType;
use App\Models\Payment;
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
        $rawIn = $request->input('check_in', $request->input('trip_date'));
        $rawOut = $request->input('check_out', $request->input('return_date'));
        $checkIn = $this->parseDate($rawIn);
        $checkOut = $this->parseDate($rawOut);
        $bothDateParamsBlank = $this->isBlankDateParam($rawIn) && $this->isBlankDateParam($rawOut);

        if ($debug) {
            $this->emitHotelSearchDebug('request', [
                'endpoint' => 'GET /api/v1/hotel/search',
                'query' => $request->query(),
                'body' => $request->except(array_keys($request->query())),
                'raw_dates' => [
                    'check_in' => $rawIn,
                    'check_out' => $rawOut,
                ],
                'resolved' => [
                    'city' => $city,
                    'check_in' => $checkIn?->toDateString(),
                    'check_out' => $checkOut?->toDateString(),
                ],
                'dates_ok' => $checkIn && $checkOut && $checkOut > $checkIn,
                'both_date_params_blank' => $bothDateParamsBlank,
            ]);
        }

        if ((! $checkIn || ! $checkOut || $checkOut <= $checkIn)
            && $bothDateParamsBlank
            && (bool) config('hotel.search_default_stay_when_dates_missing', true)) {
            $checkIn = Carbon::today()->startOfDay();
            $checkOut = $checkIn->copy()->addDay();
            if ($debug) {
                $this->emitHotelSearchDebug('dates_defaulted', [
                    'check_in' => $checkIn->toDateString(),
                    'check_out' => $checkOut->toDateString(),
                    'reason' => 'both_check_in_and_check_out_missing_used_today_and_tomorrow',
                ]);
            }
        }

        if (! $checkIn || ! $checkOut || $checkOut <= $checkIn) {
            if ($debug) {
                $this->emitHotelSearchDebug('empty', [
                    'reason' => 'invalid_or_missing_dates',
                    'check_in_raw' => $rawIn,
                    'check_out_raw' => $rawOut,
                ]);
            }

            return [];
        }

        $limit = max(1, min(100, (int) config('hotel.search_default_limit', 30)));
        $radiusKm = max(1.0, min(500.0, (float) config('hotel.search_radius_km', 50)));

        $q = Hotel::query();
        $this->applyHotelsSearchVisibilityFilter($q);

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
            $cityIdMatches = [];
            if ($this->hotelsTableHasCityId() && Schema::hasTable('cities')) {
                $t = (string) trim($city);
                $cityIdMatches = DB::table('cities')
                    ->where(function ($c) use ($like, $t) {
                        $c->whereRaw('LOWER(cities.name) LIKE LOWER(?)', [$like])
                            ->orWhereRaw('LOWER(TRIM(cities.name)) = LOWER(?)', [$t]);
                    })
                    ->pluck('id')
                    ->all();
            }

            $q->where(function ($w) use ($like, $cityIdMatches) {
                $w->whereRaw('LOWER(hotels.name) LIKE LOWER(?)', [$like]);
                if ($this->hotelsTableHasCityString()) {
                    $w->orWhereRaw('LOWER(hotels.city) LIKE LOWER(?)', [$like]);
                }
                if ($this->hotelsTableHasCityId() && Schema::hasTable('cities')) {
                    if ($cityIdMatches !== []) {
                        $w->orWhereIn('hotels.city_id', $cityIdMatches);
                    }
                    $w->orWhereExists(function ($sub) use ($like) {
                        $sub->selectRaw('1')
                            ->from('cities')
                            ->whereColumn('cities.id', 'hotels.city_id')
                            ->whereRaw('LOWER(cities.name) LIKE LOWER(?)', [$like]);
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
            $bounds = $this->nightlyListMinMaxForHotel((int) $hotel->id);
            $min = $bounds['min'];
            $max = $bounds['max'];
            if ($min === null) {
                $p = $this->minListPriceFromHotelRecord($hotel);
                if ($p !== null) {
                    $min = $p;
                    $max = $p;
                }
            }
            // Panel often creates a hotel before any room inventory rows exist — still show in search.
            if ($min === null && ! $this->hotelHasAnyRoomInventory((int) $hotel->id)) {
                $min = 0.0;
                $max = 0.0;
                if ($debug) {
                    $this->emitHotelSearchDebug('list_price_default', [
                        'hotel_id' => $hotel->id,
                        'name' => $hotel->name,
                        'note' => 'no rows in hotel_room_types / hotel_rooms; listing with price_per_night=0',
                    ]);
                }
            }
            if ($min === null) {
                if ($debug) {
                    $skippedNoPricedRoom[] = [
                        'hotel_id' => $hotel->id,
                        'name' => $hotel->name,
                        'reason' => 'no_resolvable_nightly_rate',
                        'hint' => 'Room types exist but all price fields are null, and no list price on hotels.',
                    ];
                }

                continue;
            }
            if ($max === null) {
                $max = $min;
            }
            if ($min > $max) {
                $tmp = $min;
                $min = $max;
                $max = $tmp;
            }
            $photoUrl = $this->firstHotelPhotoUrlForSearch($hotel);
            $cityLabel = $this->resolveHotelCityLabel($hotel, $cityNamesById);
            $stars = $this->resolveHotelStars($hotel);
            $amenities = $this->facilityNamesForHotel((int) $hotel->id);
            $out[] = [
                'id' => $hotel->id,
                'hotel_id' => $hotel->id,
                'name' => $hotel->name,
                'location' => $cityLabel ?? $hotel->address ?? '',
                'city' => $cityLabel,
                'photo' => $photoUrl,
                'stars' => $stars,
                'rating' => (float) ($hotel->getAttribute('aggregate_rating') ?? $hotel->getAttribute('rating') ?? 0),
                'reviews' => (int) ($hotel->review_count ?? 0),
                'price_per_night' => (float) $min,
                'price_per_night_max' => (float) $max,
                'currency' => 'BDT',
                'amenities' => $amenities,
                'facilities' => $amenities,
            ];
        }

        usort($out, function (array $a, array $b) {
            return ($a['price_per_night'] <=> $b['price_per_night'])
                ?: (strcmp((string) $a['name'], (string) $b['name']));
        });

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
                    'room_types_nightly_coalesce' => $this->nightlyPriceCoalesceSql(),
                ],
            ]);
        }

        return $out;
    }

    /**
     * Home horizontal list: top-rated visible hotels, same card shape as hotel/search.
     */
    public function homeTopHotels(Request $request): array
    {
        $limit = max(1, min(20, (int) $request->query('limit', 8)));

        $q = Hotel::query();
        $this->applyHotelsSearchVisibilityFilter($q);

        if (Schema::hasColumn($this->hotelsTable(), 'aggregate_rating')) {
            $q->orderByDesc('aggregate_rating');
        }
        if (Schema::hasColumn($this->hotelsTable(), 'review_count')) {
            $q->orderByDesc('review_count');
        }
        $q->orderBy('name');
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
        foreach ($hotels as $hotel) {
            $bounds = $this->nightlyListMinMaxForHotel((int) $hotel->id);
            $min = $bounds['min'];
            $max = $bounds['max'];
            if ($min === null) {
                $p = $this->minListPriceFromHotelRecord($hotel);
                if ($p !== null) {
                    $min = $p;
                    $max = $p;
                }
            }
            if ($min === null && ! $this->hotelHasAnyRoomInventory((int) $hotel->id)) {
                $min = 0.0;
                $max = 0.0;
            }
            if ($min === null) {
                continue;
            }
            if ($max === null) {
                $max = $min;
            }
            if ($min > $max) {
                $tmp = $min;
                $min = $max;
                $max = $tmp;
            }
            $photoUrl = $this->firstHotelPhotoUrlForSearch($hotel);
            $cityLabel = $this->resolveHotelCityLabel($hotel, $cityNamesById);
            $stars = $this->resolveHotelStars($hotel);
            $amenities = $this->facilityNamesForHotel((int) $hotel->id);
            $out[] = [
                'id' => $hotel->id,
                'hotel_id' => $hotel->id,
                'name' => $hotel->name,
                'location' => $cityLabel ?? $hotel->address ?? '',
                'city' => $cityLabel,
                'photo' => $photoUrl,
                'stars' => $stars,
                'rating' => (float) ($hotel->getAttribute('aggregate_rating') ?? $hotel->getAttribute('rating') ?? 0),
                'reviews' => (int) ($hotel->review_count ?? 0),
                'price_per_night' => (float) $min,
                'price_per_night_max' => (float) $max,
                'currency' => 'BDT',
                'amenities' => $amenities,
                'facilities' => $amenities,
            ];
        }

        return $out;
    }

    /**
     * Public list: typically status=1 and/or is_active=1. String statuses (active/published)
     * are only OR-ed when the `status` column is not a plain integer, to avoid MySQL
     * coercing e.g. 'active' to 0 and matching every row with status=0.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<Hotel>  $q
     */
    private function applyHotelsSearchVisibilityFilter($q): void
    {
        $t = 'hotels';
        $q->where(function ($w) use ($t) {
            $w->where("{$t}.status", 1)
                ->orWhere("{$t}.status", '1');
            if (Schema::hasColumn($t, 'is_active')) {
                $w->orWhere("{$t}.is_active", 1);
            }
            if (Schema::hasColumn($t, 'status')) {
                $ct = Schema::getColumnType($t, 'status');
                if (! in_array($ct, ['integer', 'int', 'bigint', 'smallint', 'tinyint', 'boolean'], true)) {
                    $w->orWhereIn("{$t}.status", [
                        'active', 'ACTIVE', 'published', 'PUBLISHED', 'enabled', 'ENABLED', 'true', 'TRUE',
                    ]);
                }
            }
        });

        // Customer/agent public search: only Durpalla-admin approved hotels.
        \App\Support\PublicListingVisibility::applyApprovedHotel($q, $t);
    }

    private function roomTypesTable(): string
    {
        return (new HotelRoomType)->getTable();
    }

    /**
     * SQL fragment: COALESCE(first non-null among known nightly price columns).
     * Legacy / panel schemas may use `price` instead of `base_price_per_night`.
     */
    private function nightlyPriceCoalesceSql(): ?string
    {
        $t = $this->roomTypesTable();
        $cols = [];
        foreach ([
            'base_price_per_night',
            'price',
            'base_price',
            'rate',
            'nightly_rate',
            'room_rate',
            'adult_rate',
            'b2c_price',
            'b2b_price',
            'rate_per_night',
            'night_rate',
            'per_night_price',
            'amount',
        ] as $col) {
            if (Schema::hasColumn($t, $col)) {
                $cols[] = "`{$t}`.`{$col}`";
            }
        }

        if ($cols === []) {
            return null;
        }

        return 'COALESCE('.implode(', ', $cols).')';
    }

    /**
     * Nightly min/max for search list (low → high within each hotel) and `price_per_night` (min) sorting.
     *
     * 1) Phase-1 API: `hotel_room_types` + COALESCE of known price columns.
     * 2) Module: `hotel_rooms` + `base_price`.
     *
     * @return array{min: ?float, max: ?float}
     */
    private function nightlyListMinMaxForHotel(int $hotelId): array
    {
        $t = $this->roomTypesTable();
        if (Schema::hasTable($t)) {
            $expr = $this->nightlyPriceCoalesceSql();
            if ($expr !== null) {
                foreach ([true, false] as $applyPublishScope) {
                    $q = HotelRoomType::query()->where("{$t}.hotel_id", $hotelId);
                    if ($applyPublishScope) {
                        $this->applyRoomTypePublishScope($q, $t);
                    }
                    $row = $q->selectRaw("MIN({$expr}) as __min, MAX({$expr}) as __max")->first();
                    if ($row !== null && $row->__min !== null) {
                        $lo = (float) $row->__min;
                        $hi = $row->__max !== null ? (float) $row->__max : $lo;

                        return ['min' => $lo, 'max' => $hi];
                    }
                }

                $qAny = HotelRoomType::query()->where("{$t}.hotel_id", $hotelId);
                if ($qAny->exists()) {
                    $row2 = (clone $qAny)->selectRaw("MAX({$expr}) as __max")->first();
                    $hi = $row2 && $row2->__max !== null ? (float) $row2->__max : 0.0;

                    return ['min' => 0.0, 'max' => $hi];
                }
            }
        }

        $m = $this->minMaxPriceFromModuleHotelRooms($hotelId);
        if ($m !== null) {
            return ['min' => $m['min'], 'max' => $m['max']];
        }

        return ['min' => null, 'max' => null];
    }

    /**
     * Min and max of `base_price` on `hotel_rooms` (Module Hotel) with the same scope as the old min-only helper.
     *
     * @return array{min: float, max: float}|null
     */
    private function minMaxPriceFromModuleHotelRooms(int $hotelId): ?array
    {
        if (! Schema::hasTable('hotel_rooms') || ! Schema::hasColumn('hotel_rooms', 'base_price')) {
            return null;
        }
        $q = DB::table('hotel_rooms')->where('hotel_id', $hotelId);
        if (Schema::hasColumn('hotel_rooms', 'deleted_at')) {
            $q->whereNull('deleted_at');
        }
        if (Schema::hasColumn('hotel_rooms', 'status')) {
            $q->where(function ($w) {
                $w->whereNull('status')->orWhere('status', 1)->orWhere('status', '1');
            });
        }
        $row = $q->selectRaw('MIN(base_price) as __min, MAX(base_price) as __max')->first();
        if ($row !== null && $row->__min !== null) {
            $lo = (float) $row->__min;
            $hi = $row->__max !== null ? (float) $row->__max : $lo;

            return ['min' => $lo, 'max' => $hi];
        }
        $q2 = DB::table('hotel_rooms')->where('hotel_id', $hotelId);
        if (Schema::hasColumn('hotel_rooms', 'deleted_at')) {
            $q2->whereNull('deleted_at');
        }
        if (Schema::hasColumn('hotel_rooms', 'status')) {
            $q2->where(function ($w) {
                $w->whereNull('status')->orWhere('status', 1)->orWhere('status', '1');
            });
        }
        if ($q2->exists()) {
            return ['min' => 0.0, 'max' => 0.0];
        }

        return null;
    }

    /**
     * List / "from" price on the `hotels` row (some admin UIs only store a banner price there).
     */
    private function minListPriceFromHotelRecord(Hotel $hotel): ?float
    {
        $t = $this->hotelsTable();
        foreach ([
            'min_nightly_price',
            'min_price',
            'starting_from_price',
            'list_price',
            'from_price',
            'nightly_from',
            'cheapest_nightly',
            'base_nightly',
            'b2c_min_price',
            'per_night_from',
        ] as $col) {
            if (! Schema::hasColumn($t, $col)) {
                continue;
            }
            $v = $hotel->getAttribute($col);
            if ($v === null || $v === '') {
                continue;
            }
            $f = (float) $v;
            if ($f < 0) {
                continue;
            }

            return $f;
        }

        return null;
    }

    private function hotelHasAnyRoomTypeRow(int $hotelId): bool
    {
        $t = $this->roomTypesTable();
        if (! Schema::hasTable($t)) {
            return false;
        }

        return HotelRoomType::query()->where("{$t}.hotel_id", $hotelId)->exists();
    }

    /** True if the hotel has inventory in API `hotel_room_types` and/or Module `hotel_rooms`. */
    private function hotelHasAnyRoomInventory(int $hotelId): bool
    {
        if ($this->hotelHasAnyRoomTypeRow($hotelId)) {
            return true;
        }
        if (! Schema::hasTable('hotel_rooms')) {
            return false;
        }
        $q = DB::table('hotel_rooms')->where('hotel_id', $hotelId);
        if (Schema::hasColumn('hotel_rooms', 'deleted_at')) {
            $q->whereNull('deleted_at');
        }

        return $q->exists();
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<\App\Models\HotelRoomType>  $q
     */
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

    /**
     * @param  array<string, mixed>  $context
     */
    private function emitHotelSearchDebug(string $phase, array $context): void
    {
        $context['phase'] = $phase;
        $line = '[hotel.search] '.$phase.' '.json_encode($context, JSON_UNESCAPED_UNICODE);
        error_log($line);
        Log::warning('[hotel.search] '.$phase, $context);
    }

    private function hotelImagesTableHasTypeColumn(): bool
    {
        return Schema::hasTable('hotel_images') && Schema::hasColumn('hotel_images', 'type');
    }

    private function isModuleHotelImageCoverType(?string $type): bool
    {
        if ($type === null || trim($type) === '') {
            return false;
        }

        return in_array(strtolower(trim($type)), ['cover', 'hero', 'header'], true);
    }

    /**
     * List thumbnail: `hotel_images` rows typed cover/hero/header, else legacy `hotel_photos`, else any module image.
     */
    private function firstHotelPhotoUrlForSearch(Hotel $hotel): string
    {
        if (Schema::hasTable('hotel_images') && $this->hotelImagesTableHasTypeColumn()) {
            $row = DB::table('hotel_images')
                ->where('hotel_id', $hotel->id)
                ->whereNotNull('type')
                ->whereRaw("LOWER(TRIM(type)) IN ('cover', 'hero', 'header')")
                ->orderBy('sort_order')
                ->orderBy('id')
                ->first();
            if ($row !== null) {
                $u = $this->normalizeHotelImageUrl((string) ($row->image_path ?? ''));
                if ($u !== '') {
                    return $u;
                }
            }
        }

        $p = $hotel->photos()->orderBy('sort_order')->orderBy('id')->first();
        if ($p !== null) {
            $u = $this->normalizeHotelImageUrl((string) ($p->url ?? ''));
            if ($u !== '') {
                return $u;
            }
        }
        if (Schema::hasTable('hotel_images')) {
            if ($this->hotelImagesTableHasTypeColumn()) {
                $row = DB::table('hotel_images')
                    ->where('hotel_id', $hotel->id)
                    ->where(function ($w) {
                        $w->whereNull('type')
                            ->orWhere('type', '')
                            ->orWhereRaw("LOWER(TRIM(type)) NOT IN ('cover', 'hero', 'header')");
                    })
                    ->orderBy('sort_order')
                    ->orderBy('id')
                    ->first();
            } else {
                $row = DB::table('hotel_images')
                    ->where('hotel_id', $hotel->id)
                    ->orderBy('sort_order')
                    ->orderBy('id')
                    ->first();
            }
            if ($row !== null) {
                $u = $this->normalizeHotelImageUrl((string) ($row->image_path ?? ''));
                if ($u !== '') {
                    return $u;
                }
            }
        }

        return '';
    }

    /**
     * Module Hotel facility names linked via hotel_facility_hotel (admin Facilities tab).
     *
     * @return list<string>
     */
    private function facilityNamesForHotel(int $hotelId): array
    {
        if (! Schema::hasTable('hotel_facility_hotel') || ! Schema::hasTable('hotel_facilities')) {
            return [];
        }

        return DB::table('hotel_facility_hotel as pivot')
            ->join('hotel_facilities as f', 'f.id', '=', 'pivot.hotel_facility_id')
            ->where('pivot.hotel_id', $hotelId)
            ->orderBy('f.name')
            ->pluck('f.name')
            ->map(fn ($name) => trim((string) $name))
            ->filter(fn ($name) => $name !== '')
            ->values()
            ->all();
    }

    /**
     * Module hotel_rooms.id from API room type code `mod_hr_{id}`.
     */
    private function moduleHotelRoomIdFromApiCode(?string $code): ?int
    {
        if ($code === null || $code === '') {
            return null;
        }
        if (! preg_match('/^mod_hr_(\d+)$/', $code, $m)) {
            return null;
        }

        return (int) $m[1];
    }

    /**
     * Room-level facility names via hotel_room_facility (admin Rooms tab).
     *
     * @return list<string>
     */
    private function facilityNamesForHotelRoom(int $hotelRoomId): array
    {
        if ($hotelRoomId <= 0
            || ! Schema::hasTable('hotel_room_facility')
            || ! Schema::hasTable('hotel_facilities')) {
            return [];
        }

        return DB::table('hotel_room_facility as pivot')
            ->join('hotel_facilities as f', 'f.id', '=', 'pivot.hotel_facility_id')
            ->where('pivot.hotel_room_id', $hotelRoomId)
            ->orderBy('f.name')
            ->pluck('f.name')
            ->map(fn ($name) => trim((string) $name))
            ->filter(fn ($name) => $name !== '')
            ->values()
            ->all();
    }

    /**
     * Room gallery from module `hotel_room_images`.
     *
     * @return list<array{url: string}>
     */
    private function moduleHotelRoomGalleryAsApiPhotos(int $hotelRoomId): array
    {
        if ($hotelRoomId <= 0 || ! Schema::hasTable('hotel_room_images')) {
            return [];
        }

        $out = [];
        $rows = DB::table('hotel_room_images')
            ->where('hotel_room_id', $hotelRoomId)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
        foreach ($rows as $row) {
            $u = $this->normalizeHotelImageUrl((string) ($row->image_path ?? ''));
            if ($u === '') {
                continue;
            }
            $out[] = ['url' => $u];
        }

        return $out;
    }

    /**
     * Photos for an API room type: legacy hotel_room_type_photos, else module hotel_room_images.
     *
     * @return list<array{url: string}>
     */
    private function photosForApiRoomType(HotelRoomType $rt): array
    {
        $roomPhotos = [];
        foreach ($rt->photos as $p) {
            $u = $this->normalizeHotelImageUrl((string) ($p->url ?? ''));
            if ($u === '') {
                continue;
            }
            $roomPhotos[] = ['url' => $u];
        }
        if ($roomPhotos !== []) {
            return $roomPhotos;
        }

        $hrId = $this->moduleHotelRoomIdFromApiCode((string) $rt->code);

        return $hrId !== null ? $this->moduleHotelRoomGalleryAsApiPhotos($hrId) : [];
    }

    /**
     * Amenities for an API room type: stored JSON, else module hotel_room_facility names.
     *
     * @return list<string>
     */
    private function amenitiesForApiRoomType(HotelRoomType $rt): array
    {
        $stored = $rt->amenities ?? [];
        if (is_array($stored) && $stored !== []) {
            return array_values(array_filter(array_map(
                fn ($name) => trim((string) $name),
                $stored
            ), fn ($name) => $name !== ''));
        }

        $hrId = $this->moduleHotelRoomIdFromApiCode((string) $rt->code);

        return $hrId !== null ? $this->facilityNamesForHotelRoom($hrId) : [];
    }

    /**
     * @return list<array{url: string, caption: ?string}>
     */
    private function moduleHotelGalleryAsApiPhotos(int $hotelId, bool $excludeCoverTypes): array
    {
        if (! Schema::hasTable('hotel_images')) {
            return [];
        }
        $q = DB::table('hotel_images')
            ->where('hotel_id', $hotelId)
            ->orderBy('sort_order')
            ->orderBy('id');
        if ($excludeCoverTypes && $this->hotelImagesTableHasTypeColumn()) {
            $q->where(function ($w) {
                $w->whereNull('type')
                    ->orWhere('type', '')
                    ->orWhereRaw("LOWER(TRIM(type)) NOT IN ('cover', 'hero', 'header')");
            });
        }
        $out = [];
        foreach ($q->get() as $row) {
            if ($this->isModuleHotelImageCoverType(
                is_string($row->type ?? null) ? (string) $row->type : (isset($row->type) ? (string) $row->type : null)
            )) {
                continue;
            }
            $u = $this->normalizeHotelImageUrl((string) ($row->image_path ?? ''));
            if ($u === '') {
                continue;
            }
            $out[] = ['url' => $u, 'caption' => null];
        }

        return $out;
    }

    private function firstModuleCoverImageUrlForHotel(int $hotelId): string
    {
        if (! Schema::hasTable('hotel_images') || ! $this->hotelImagesTableHasTypeColumn()) {
            return '';
        }
        $row = DB::table('hotel_images')
            ->where('hotel_id', $hotelId)
            ->whereNotNull('type')
            ->whereRaw("LOWER(TRIM(type)) IN ('cover', 'hero', 'header')")
            ->orderBy('sort_order')
            ->orderBy('id')
            ->first();
        if ($row === null) {
            return '';
        }
        $u = $this->normalizeHotelImageUrl((string) ($row->image_path ?? ''));

        return $u;
    }

    private function normalizeHotelImageUrl(string $raw): string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return '';
        }
        if (str_starts_with($raw, '//')) {
            $app = (string) config('app.url', '');
            $scheme = $app !== '' && str_contains($app, '://') ? (parse_url($app, PHP_URL_SCHEME) ?: 'https') : 'https';
            $raw = $scheme.':'.$raw;
        }
        if (preg_match('#^https?://#i', $raw) === 1) {
            return $this->rewriteHotelStorageUrlToImagePublicBase($raw);
        }
        $base = rtrim((string) config('hotel.image_public_base_url', ''), '/');
        if ($base === '') {
            $base = rtrim((string) config('app.url', ''), '/');
        }
        $path = str_replace('\\', '/', $raw);
        if (str_starts_with($path, '/')) {
            return $base.$path;
        }
        if (preg_match('#^storage/#i', $path) === 1) {
            return $base.'/'.ltrim($path, '/');
        }

        return $base.'/storage/'.ltrim($path, '/');
    }

    /**
     * Panel uploads often persist full URLs using APP_URL (e.g. apigw) while the symlinked
     * `public/storage` files are only (or also) served from the admin app. Rewrites
     * `/storage/...` absolute URLs to `hotel.image_public_base_url` (e.g. https://admin.durpalla.com)
     * so mobile clients request the host that actually has the file.
     */
    private function rewriteHotelStorageUrlToImagePublicBase(string $url): string
    {
        $base = rtrim((string) config('hotel.image_public_base_url', ''), '/');
        if ($base === '') {
            return $url;
        }
        $u = parse_url($url);
        if ($u === false || empty($u['path']) || ! str_starts_with($u['path'], '/storage/')) {
            return $url;
        }
        $b = parse_url($base);
        if ($b === false || ($b['scheme'] ?? '') === '' || ($b['host'] ?? '') === '') {
            return $url;
        }
        $out = $b['scheme'].'://'.$b['host'];
        if (! empty($b['port'])) {
            $out .= ':'.$b['port'];
        }
        $out .= $u['path'];
        if (! empty($u['query'])) {
            $out .= '?'.$u['query'];
        }
        if (! empty($u['fragment'])) {
            $out .= '#'.$u['fragment'];
        }

        return $out;
    }

    public function hotelDetails(int $hotelId): ?array
    {
        $hotel = Hotel::query()->with(['photos', 'reviews'])->find($hotelId);
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

        $legacyPhotos = [];
        foreach ($hotel->photos as $p) {
            $u = $this->normalizeHotelImageUrl((string) ($p->url ?? ''));
            if ($u !== '') {
                $legacyPhotos[] = ['url' => $u, 'caption' => $p->caption];
            }
        }

        $hasImgType = $this->hotelImagesTableHasTypeColumn();
        $moduleCover = $hasImgType ? $this->firstModuleCoverImageUrlForHotel($hotel->id) : '';
        $moduleGallery = $this->moduleHotelGalleryAsApiPhotos($hotel->id, $hasImgType);

        if ($hasImgType) {
            $galleryPhotos = $moduleGallery;
            foreach ($legacyPhotos as $item) {
                $u = (string) ($item['url'] ?? '');
                if ($u === '') {
                    continue;
                }
                foreach ($galleryPhotos as $g) {
                    if (($g['url'] ?? '') === $u) {
                        continue 2;
                    }
                }
                $galleryPhotos[] = $item;
            }
            if ($galleryPhotos === [] && $legacyPhotos !== []) {
                $galleryPhotos = $legacyPhotos;
            }
            $hero = $moduleCover !== '' ? $moduleCover : (($legacyPhotos[0]['url'] ?? '') !== '' ? $legacyPhotos[0]['url'] : (string) ($moduleGallery[0]['url'] ?? ''));
        } else {
            $galleryPhotos = $legacyPhotos !== [] ? $legacyPhotos : $this->moduleHotelGalleryAsApiPhotos($hotel->id, false);
            $hero = ($legacyPhotos[0]['url'] ?? '') !== ''
                ? (string) $legacyPhotos[0]['url']
                : (string) ($galleryPhotos[0]['url'] ?? '');
        }

        $facilityNames = $this->facilityNamesForHotel($hotel->id);

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
            'photo' => $hero,
            'cover_photo' => $hero,
            'gallery' => $galleryPhotos,
            'photos' => $galleryPhotos,
            'amenities' => $facilityNames,
            'facilities' => $facilityNames,
            'reviews' => $hotel->reviews->map(fn ($r) => [
                'author' => $r->author,
                'rating' => (float) $r->rating,
                'text' => $r->body,
                'date' => $r->reviewed_at?->toDateString(),
            ])->values()->all(),
            /** Published room catalog (no per-stay quote; use [GET] hotel/{id}/rooms for availability + quote). */
            'room_types' => $this->roomTypesCatalogForHotel($hotel->id),
        ];
    }

    /**
     * Module Hotel stores bookable rows in `hotel_rooms` (+ `room_types`); the customer API
     * uses `hotel_room_types`. Upsert one API row per active `hotel_rooms` row so details/rooms
     * match the admin "Rooms" tab (stable code: mod_hr_{hotel_rooms.id}).
     */
    private function syncModuleHotelRoomsIntoApiRoomTypes(int $hotelId): void
    {
        if (! Schema::hasTable('hotel_rooms')) {
            return;
        }
        $apiTable = $this->roomTypesTable();
        if (! Schema::hasTable($apiTable)) {
            return;
        }

        $q = $this->queryActiveModuleHotelRooms($hotelId, strict: true);
        $rows = $q->orderBy('id')->get();
        if ($rows->isEmpty()) {
            $rows = $this->queryActiveModuleHotelRooms($hotelId, strict: false)->orderBy('id')->get();
        }
        $activeHrIds = $rows->pluck('id')->map(fn ($id) => (int) $id)->all();

        $roomTypeNames = [];
        $roomTypeCategories = [];
        if (Schema::hasTable('room_types')) {
            $ids = $rows->pluck('room_type_id')->filter()->unique()->values()->all();
            if ($ids !== []) {
                $typeRows = DB::table('room_types')->whereIn('id', $ids)->get();
                foreach ($typeRows as $typeRow) {
                    $roomTypeNames[$typeRow->id] = $typeRow->name;
                    $roomTypeCategories[$typeRow->id] = Schema::hasColumn('room_types', 'category')
                        ? ($typeRow->category ?? HotelRoomType::CATEGORY_ROOM)
                        : $this->inferRoomCategory((string) $typeRow->name);
                }
            }
        }

        foreach ($rows as $hr) {
            $code = 'mod_hr_'.$hr->id;
            $typeName = $roomTypeNames[$hr->room_type_id] ?? null;
            $title = $this->resolveModuleHotelRoomTitle($hr, $typeName);
            $baseFloat = ($hr->base_price !== null && $hr->base_price !== '') ? (float) $hr->base_price : 0.0;

            $payload = [
                'title' => $title,
                'max_occupancy' => max(1, (int) $hr->max_occupancy),
                'bed_type' => null,
                'amenities' => $this->facilityNamesForHotelRoom((int) $hr->id),
                'base_price_per_night' => $baseFloat,
                'currency' => 'BDT',
                'status' => 1,
            ];
            if (Schema::hasColumn($apiTable, 'category')) {
                $payload['category'] = $roomTypeCategories[$hr->room_type_id]
                    ?? $this->inferRoomCategory($title);
            }
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

    private function resolveModuleHotelRoomTitle(object $hr, ?string $typeName): string
    {
        $type = $typeName !== null ? trim((string) $typeName) : '';
        $name = trim((string) ($hr->name ?? ''));

        if ($name !== '') {
            if (preg_match('/^Room\s+(.+)$/i', $name, $m)) {
                $rest = trim($m[1]);
                if ($rest !== '') {
                    return $this->stripRoomSuffix($rest);
                }
            }
            if (strcasecmp($name, 'Room') === 0 && $type !== '') {
                return $this->stripRoomSuffix($type);
            }

            return $this->stripRoomSuffix($name);
        }

        if ($type !== '') {
            return $this->stripRoomSuffix($type);
        }

        return 'Type '.$hr->id;
    }

    private function stripRoomSuffix(string $title): string
    {
        $title = trim($title);
        $clean = trim(preg_replace('/\s+Room$/i', '', $title) ?? $title);

        return $clean !== '' ? $clean : 'Standard';
    }

    private function inferRoomCategory(string $title): string
    {
        $title = strtolower($title);
        if (str_contains($title, 'suite')) {
            return HotelRoomType::CATEGORY_SUITE;
        }
        if (str_contains($title, 'apartment')) {
            return HotelRoomType::CATEGORY_APARTMENT;
        }

        return HotelRoomType::CATEGORY_ROOM;
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

    /**
     * Published room type rows for hotel details (code, title, photos, base rate hint).
     *
     * @return list<array<string, mixed>>
     */
    private function roomTypesCatalogForHotel(int $hotelId): array
    {
        $this->syncModuleHotelRoomsIntoApiRoomTypes($hotelId);
        $t = $this->roomTypesTable();
        $typesQ = HotelRoomType::query()->where("{$t}.hotel_id", $hotelId);
        $this->applyRoomTypePublishScope($typesQ, $t);
        $types = $typesQ->with('photos')->orderBy("{$t}.id")->get();

        $out = [];
        foreach ($types as $rt) {
            $amenities = $this->amenitiesForApiRoomType($rt);
            $out[] = [
                'id' => $rt->id,
                'room_type_id' => $rt->id,
                'code' => $rt->code,
                'title' => $rt->displayTitle(),
                'category' => $rt->accommodationCategory(),
                'max_occupancy' => (int) $rt->max_occupancy,
                'bed_type' => $rt->bed_type,
                'amenities' => $amenities,
                'facilities' => $amenities,
                'photos' => $this->photosForApiRoomType($rt),
                'base_price_per_night' => $rt->base_price_per_night !== null
                    ? (float) $rt->base_price_per_night
                    : null,
                'currency' => (string) ($rt->currency ?: 'BDT'),
            ];
        }

        return $out;
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
        $this->syncModuleHotelRoomsIntoApiRoomTypes($hotelId);
        $checkIn = $this->parseDate($request->input('check_in', $request->input('trip_date')));
        $checkOut = $this->parseDate($request->input('check_out', $request->input('return_date')));
        $adults = max(1, (int) $request->input('adults', 2));
        $children = max(0, (int) $request->input('children', 0));
        if (! $checkIn || ! $checkOut || $checkOut <= $checkIn) {
            return [];
        }

        $t = $this->roomTypesTable();
        $typesQ = HotelRoomType::query()->where("{$t}.hotel_id", $hotelId);
        $this->applyRoomTypePublishScope($typesQ, $t);
        $types = $typesQ->with('photos')->get();

        $out = [];
        $relaxInv = (bool) config('hotel.rooms_treat_missing_inventory_as_available', true);
        $guests = $adults + $children;
        foreach ($types as $rt) {
            $maxOccupancy = max(1, (int) $rt->max_occupancy);
            $roomsNeeded = (int) max(1, (int) ceil($guests / $maxOccupancy));
            $availableCount = $this->inventory->availableUnits($rt, $checkIn, $checkOut);
            try {
                $this->inventory->assertAvailability($rt, $checkIn, $checkOut, $roomsNeeded);
                $available = true;
            } catch (\Throwable) {
                $available = $relaxInv;
            }
            $quote = $this->pricing->quoteStay($rt, $checkIn, $checkOut, $adults, $children);
            $amenities = $this->amenitiesForApiRoomType($rt);
            $out[] = [
                'id' => $rt->id,
                'room_type_id' => $rt->id,
                'code' => $rt->code,
                'title' => $rt->displayTitle(),
                'category' => $rt->accommodationCategory(),
                'max_occupancy' => $maxOccupancy,
                'bed_type' => $rt->bed_type,
                'amenities' => $amenities,
                'facilities' => $amenities,
                'photos' => $this->photosForApiRoomType($rt),
                'available' => $available,
                'available_count' => $availableCount,
                'available_rooms' => $availableCount,
                'rooms_needed' => $roomsNeeded,
                'quote' => $quote,
            ];
        }

        return $out;
    }

    /**
     * Human-readable reason when [roomsForStay] returns an empty list (for API `message` on 200).
     */
    public function describeEmptyHotelRooms(Request $request, int $hotelId): ?string
    {
        if (! Hotel::query()->whereKey($hotelId)->exists()) {
            return 'Hotel not found.';
        }
        $this->syncModuleHotelRoomsIntoApiRoomTypes($hotelId);
        $checkIn = $this->parseDate($request->input('check_in', $request->input('trip_date')));
        $checkOut = $this->parseDate($request->input('check_out', $request->input('return_date')));

        if (! $checkIn || ! $checkOut || $checkOut <= $checkIn) {
            return 'Valid check-in and check-out are required, and check-out must be after check-in.';
        }

        $t = $this->roomTypesTable();
        $typesQ = HotelRoomType::query()->where("{$t}.hotel_id", $hotelId);
        $this->applyRoomTypePublishScope($typesQ, $t);
        $types = $typesQ->get();

        if ($types->isEmpty()) {
            return 'This property has no bookable room types set up yet. Try another hotel, or check back after the property adds room inventory.';
        }

        return 'No rooms are available for this stay. Try different dates or contact the property.';
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
    public function createHold(Customer $user, array $input, string $idempotencyKey): HotelHold
    {
        $existing = HotelHold::query()
            ->where('idempotency_key', $idempotencyKey)
            ->where('user_id', $user->id)
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
            throw new \InvalidArgumentException('Provide lines or room_type_id');
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
            $mergedQty[$rid] = ($mergedQty[$rid] ?? 0) + $qty;
        }
        foreach ($mergedQty as $rid => $qty) {
            $mergedQty[$rid] = min(20, $qty);
        }
        if (count($mergedQty) > 20) {
            throw new \InvalidArgumentException('Too many distinct room types');
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
            $resolved[] = ['room_type' => $rt, 'quantity' => $quantity];
        }

        // Same customer cannot stack overlapping unpaid bookings for this stay
        // (was creating booking 65, 66, … on each confirm click).
        $this->assertNoOverlappingPendingPayment(
            (int) $user->id,
            (int) $hotelId,
            $checkIn,
            $checkOut,
        );
        // Drop any leftover open holds for this stay so a refreshed checkout can retry.
        $this->cancelOverlappingOpenHolds(
            (int) $user->id,
            (int) $hotelId,
            $checkIn,
            $checkOut,
        );

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
                'title' => $rt->displayTitle(),
                'category' => $rt->accommodationCategory(),
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

        return DB::transaction(function () use ($user, $resolved, $checkIn, $checkOut, $adults, $children, $idempotencyKey, $aggregateQuote, $ttl, $primaryRoomType) {
            foreach ($resolved as $entry) {
                $this->inventory->applyHold($entry['room_type'], $checkIn, $checkOut, $entry['quantity']);
            }

            return HotelHold::create([
                'user_id' => $user->id,
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
     * Release inventory held for a quote shape (multi-line or legacy single-room quote).
     */
    public function releaseInventoryForStoredQuote(
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
                    $this->inventory->releaseHold($rt, $checkIn, $checkOut, $ln['quantity']);
                }
            }

            return;
        }
        if ($legacyRoomType !== null) {
            $this->inventory->releaseHold($legacyRoomType, $checkIn, $checkOut, 1);
        }
    }

    /**
     * Finalize inventory after payment for a quote shape (multi-line or legacy).
     */
    public function finalizeInventoryForStoredQuote(
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
            $rid = (int) ($line['room_type_id'] ?? 0);
            $qty = max(1, (int) ($line['quantity'] ?? 1));
            if ($rid > 0) {
                $out[] = ['room_type_id' => $rid, 'quantity' => $qty];
            }
        }

        return $out;
    }

    public function releaseHold(Customer $user, int $holdId): bool
    {
        $hold = HotelHold::query()->where('id', $holdId)->where('user_id', $user->id)->first();
        if (! $hold || $hold->status !== HotelHold::STATUS_PENDING) {
            return false;
        }

        return DB::transaction(function () use ($hold) {
            $checkIn = Carbon::parse($hold->check_in);
            $checkOut = Carbon::parse($hold->check_out);
            $this->releaseInventoryForStoredQuote(
                is_array($hold->quote_json) ? $hold->quote_json : null,
                $checkIn,
                $checkOut,
                $hold->roomType,
            );
            $hold->update(['status' => HotelHold::STATUS_CANCELLED]);

            return true;
        });
    }

    /**
     * Block a second hold when the customer already has an unpaid reservation
     * overlapping the same hotel stay.
     */
    private function assertNoOverlappingPendingPayment(
        int $customerId,
        int $hotelId,
        Carbon $checkIn,
        Carbon $checkOut,
    ): void {
        $in = $checkIn->toDateString();
        $out = $checkOut->toDateString();

        $pendingPayment = HotelReservation::query()
            ->where('user_id', $customerId)
            ->where('hotel_id', $hotelId)
            ->where('status', HotelReservation::STATUS_PENDING_PAYMENT)
            ->whereDate('check_in', '<', $out)
            ->whereDate('check_out', '>', $in)
            ->where(function ($q) {
                $q->whereNull('payment_due_at')
                    ->orWhere('payment_due_at', '>', now());
            })
            ->exists();
        if ($pendingPayment) {
            throw new \RuntimeException(
                'You already have a pending payment for this hotel stay. Complete payment or wait for it to expire before booking again.'
            );
        }
    }

    /**
     * Release inventory for any other open holds this customer still has on the
     * overlapping stay so a new hold can be created cleanly.
     */
    private function cancelOverlappingOpenHolds(
        int $customerId,
        int $hotelId,
        Carbon $checkIn,
        Carbon $checkOut,
    ): void {
        $in = $checkIn->toDateString();
        $out = $checkOut->toDateString();

        $openHolds = HotelHold::query()
            ->where('user_id', $customerId)
            ->where('status', HotelHold::STATUS_PENDING)
            ->where('expires_at', '>', now())
            ->whereDate('check_in', '<', $out)
            ->whereDate('check_out', '>', $in)
            ->whereHas('roomType', fn ($q) => $q->where('hotel_id', $hotelId))
            ->get();

        foreach ($openHolds as $hold) {
            $holdIn = Carbon::parse($hold->check_in);
            $holdOut = Carbon::parse($hold->check_out);
            $this->releaseInventoryForStoredQuote(
                is_array($hold->quote_json) ? $hold->quote_json : null,
                $holdIn,
                $holdOut,
                $hold->roomType,
            );
            $hold->update(['status' => HotelHold::STATUS_CANCELLED]);
        }
    }

    /**
     * @param  array{name?:string,mobile?:string,email?:string}|null  $guest
     * @return array{reservation: HotelReservation, booking: Booking, payment: Payment}
     */
    public function confirmFromHold(
        Customer $user,
        int $holdId,
        ?string $platform = 'web',
        ?array $guest = null,
    ): array {
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

        $bookingPlatform = $this->normalizeHotelBookingPlatform($platform);
        $guestPayload = [
            'name' => trim((string) ($guest['name'] ?? '')) ?: (string) ($user->name ?? ''),
            'mobile' => trim((string) ($guest['mobile'] ?? '')) ?: (string) ($user->mobile ?? ''),
            'email' => trim((string) ($guest['email'] ?? '')) ?: (string) ($user->email ?? ''),
        ];

        return DB::transaction(function () use ($user, $hold, $bookingPlatform, $guestPayload) {
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
            if (! is_array($quote)) {
                $quote = [];
            }
            $quote['guest'] = $guestPayload;
            // Mirror transport booking fields: fare / charge / VAT-on-charge / payable.
            $fare = (float) ($quote['room_subtotal'] ?? $quote['total'] ?? 0);
            $chargeTotal = (float) ($quote['charge_amount'] ?? 0);
            $vatTotal = (float) ($quote['vat_amount'] ?? 0);
            $total = (float) ($quote['total'] ?? round($fare + $chargeTotal + $vatTotal, 2));

            $booking = Booking::create([
                'booking_date' => date('Y-m-d'),
                'customer_id' => $user->id,
                'user_id' => $user->id,
                'total_amount' => $fare,
                'total_discount' => 0,
                'total_payable' => $total,
                'vat_amount' => (float) getOption('vat_amount', 0),
                'vat_total' => $vatTotal,
                'charge_amount' => (float) ($quote['charge_percent'] ?? 0),
                'charge_total' => $chargeTotal,
                'booking_party' => 'durpalla',
                // Must be "web" for browser checkout so /payment/status redirects to
                // FRONTEND_PAYMENT_STATUS_URL instead of the apigw status HTML page.
                'platform' => $bookingPlatform,
                'status' => AppConst::BOOKING_PENDING,
                'service_type' => 'hotel',
                'from_date' => $checkIn->toDateString(),
                'to_date' => $checkOut->toDateString(),
            ]);

            $paymentWindow = max(1, (int) config('hotel.payment_window_minutes', 5));
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
                // Gateway charge amount; dues remain until payment succeeds.
                'paid_amount' => $total,
                'dues' => $total,
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
                    $checkIn = Carbon::parse($hold->check_in);
                    $checkOut = Carbon::parse($hold->check_out);
                    $this->releaseInventoryForStoredQuote(
                        is_array($hold->quote_json) ? $hold->quote_json : null,
                        $checkIn,
                        $checkOut,
                        $hold->roomType,
                    );
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
                    $checkIn = Carbon::parse($res->check_in);
                    $checkOut = Carbon::parse($res->check_out);
                    $this->releaseInventoryForStoredQuote(
                        is_array($res->quote_json) ? $res->quote_json : null,
                        $checkIn,
                        $checkOut,
                        $res->roomType,
                    );
                    $res->update(['status' => HotelReservation::STATUS_FAILED]);
                    if ($res->booking) {
                        $res->booking->update(['status' => AppConst::BOOKING_FAILED]);
                    }
                    Payment::query()
                        ->where('booking_id', $res->booking_id)
                        ->whereNotIn('status', ['success', 'paid', 'complete', 'completed', 'advance'])
                        ->update(['status' => 'fail']);
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

    private function isBlankDateParam(mixed $v): bool
    {
        if ($v === null) {
            return true;
        }
        if (is_string($v)) {
            return trim($v) === '';
        }

        return false;
    }

    /** Normalize customer hotel booking platform (defaults to web for browser checkout). */
    private function normalizeHotelBookingPlatform(?string $raw): string
    {
        $p = strtolower(trim((string) ($raw ?? 'web')));

        return match ($p) {
            'web' => 'web',
            'ios', 'iphone' => 'ios',
            'android', 'mobile', 'flutter', 'app' => 'android',
            default => 'web',
        };
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
