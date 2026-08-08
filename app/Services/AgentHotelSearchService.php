<?php

namespace App\Services;

use App\Models\Agent;
use App\Models\AgentHotelFavorite;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use App\Models\Hotel;

class AgentHotelSearchService
{
    /**
     * @return array{has_favourites:bool,favourite_hotel_ids:list<int>}
     */
    public function favouriteMeta(Agent $agent): array
    {
        $ids = AgentHotelFavorite::query()
            ->where('agent_id', $agent->id)
            ->orderByDesc('id')
            ->pluck('hotel_id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        return [
            'has_favourites' => $ids !== [],
            'favourite_hotel_ids' => $ids,
        ];
    }

    /**
     * Default: favourite hotels. When city/q is provided, full search takes over and favourites are highlighted.
     *
     * @param  array{city?:string,q?:string,check_in?:string,check_out?:string,mode?:string}  $filters
     * @return array{mode:string,items:list<array<string,mixed>>,meta:array<string,mixed>}
     */
    public function list(Agent $agent, array $filters = []): array
    {
        $meta = $this->favouriteMeta($agent);
        $city = trim((string) ($filters['city'] ?? $filters['q'] ?? ''));
        $explicitMode = strtolower(trim((string) ($filters['mode'] ?? '')));

        $isSearch = $explicitMode === 'search'
            || ($explicitMode !== 'favourites' && $city !== '');

        if ($isSearch) {
            $checkIn = trim((string) ($filters['check_in'] ?? ''));
            $checkOut = trim((string) ($filters['check_out'] ?? ''));
            $items = $this->searchHotels($city, $meta['favourite_hotel_ids']);

            return [
                'mode' => 'search',
                'items' => $items,
                'meta' => [
                    'has_favourites' => $meta['has_favourites'],
                    'favourite_count' => count($meta['favourite_hotel_ids']),
                    'mode' => 'search',
                    'query' => $city,
                    'check_in' => $checkIn !== '' ? $checkIn : null,
                    'check_out' => $checkOut !== '' ? $checkOut : null,
                    'total' => count($items),
                ],
            ];
        }

        $items = $this->favouriteHotels($meta['favourite_hotel_ids']);

        return [
            'mode' => 'favourites',
            'items' => $items,
            'meta' => [
                'has_favourites' => $meta['has_favourites'],
                'favourite_count' => count($meta['favourite_hotel_ids']),
                'mode' => 'favourites',
                'query' => '',
                'total' => count($items),
            ],
        ];
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function listFavourites(Agent $agent): Collection
    {
        $meta = $this->favouriteMeta($agent);

        return collect($this->favouriteHotels($meta['favourite_hotel_ids']));
    }

    public function addFavourite(Agent $agent, int $hotelId): array
    {
        $hotel = Hotel::query()->where('status', 1);
        \App\Support\PublicListingVisibility::applyApprovedHotel($hotel);
        $hotel = $hotel->find($hotelId);
        if (! $hotel) {
            throw new \InvalidArgumentException(__('Hotel not found'));
        }

        $favorite = AgentHotelFavorite::query()->firstOrCreate([
            'agent_id' => $agent->id,
            'hotel_id' => $hotelId,
        ]);

        $card = $this->presentHotel($hotel, true);

        return array_merge($card, [
            'created' => $favorite->wasRecentlyCreated,
        ]);
    }

    public function removeFavourite(Agent $agent, int $hotelId): bool
    {
        return AgentHotelFavorite::query()
            ->where('agent_id', $agent->id)
            ->where('hotel_id', $hotelId)
            ->delete() > 0;
    }

    /**
     * Full hotel detail for agent app (browse / favourite — not book yet).
     *
     * @return array<string, mixed>
     */
    public function detail(Agent $agent, int $hotelId): array
    {
        $hotel = Hotel::query()
            ->where('status', 1);
        \App\Support\PublicListingVisibility::applyApprovedHotel($hotel);
        $hotel = $hotel->with(['photos'])->find($hotelId);

        if (! $hotel) {
            throw new \InvalidArgumentException(__('Hotel not found'));
        }

        $favouriteIds = $this->favouriteMeta($agent)['favourite_hotel_ids'];
        $isFavourite = in_array($hotelId, $favouriteIds, true);
        $card = $this->presentHotel($hotel, $isFavourite);

        $photos = $this->hotelGallery((int) $hotel->id);
        if ($photos === [] && ! empty($card['photo'])) {
            $photos[] = ['url' => $card['photo'], 'type' => 'cover'];
        }

        $description = $this->hotelDescription($hotel);
        $rooms = $this->activeRoomsForHotel((int) $hotel->id);
        $policies = $this->hotelPolicies($hotel);
        $amenities = $this->facilityNamesForHotel((int) $hotel->id);
        $contact = $this->hotelContact((int) $hotel->id);
        $geo = $this->hotelGeo($hotel);

        return array_merge($card, [
            'photos' => $photos,
            'amenities' => $amenities,
            'description' => $description,
            'check_in_time' => $hotel->getAttribute('check_in_time') ? (string) $hotel->getAttribute('check_in_time') : null,
            'check_out_time' => $hotel->getAttribute('check_out_time') ? (string) $hotel->getAttribute('check_out_time') : null,
            'contact' => $contact,
            'geo' => $geo,
            'rooms' => $rooms,
            'policies' => $policies,
        ]);
    }

    private function imageUrl(?string $path): ?string
    {
        if ($path === null || trim($path) === '') {
            return null;
        }
        $path = (string) $path;
        if (str_starts_with($path, 'http')) {
            return $path;
        }
        if (str_starts_with($path, 'uploads/') || str_starts_with($path, 'storage/')) {
            return asset($path);
        }

        return Storage::disk(config('filesystems.profile_disk', 'public'))->url($path);
    }

    /**
     * @param  list<int>  $favouriteIds
     * @return list<array<string, mixed>>
     */
    private function favouriteHotels(array $favouriteIds): array
    {
        if ($favouriteIds === []) {
            return [];
        }

        $hotelsQuery = Hotel::query()
            ->where('status', 1)
            ->whereIn('id', $favouriteIds);
        \App\Support\PublicListingVisibility::applyApprovedHotel($hotelsQuery);
        $hotels = $hotelsQuery
            ->get()
            ->keyBy('id');

        $out = [];
        foreach ($favouriteIds as $id) {
            $hotel = $hotels->get($id);
            if (! $hotel) {
                continue;
            }
            $out[] = $this->presentHotel($hotel, true);
        }

        return $out;
    }

    /**
     * @param  list<int>  $favouriteIds
     * @return list<array<string, mixed>>
     */
    private function searchHotels(string $city, array $favouriteIds): array
    {
        $query = Hotel::query()
            ->where('status', 1);
        \App\Support\PublicListingVisibility::applyApprovedHotel($query);

        if ($city !== '') {
            $like = '%'.addcslashes($city, '%_\\').'%';
            $cityIdMatches = [];
            if (Schema::hasColumn('hotels', 'city_id') && Schema::hasTable('cities')) {
                $t = trim($city);
                $cityIdMatches = DB::table('cities')
                    ->where(function ($c) use ($like, $t) {
                        $c->whereRaw('LOWER(cities.name) LIKE LOWER(?)', [$like])
                            ->orWhereRaw('LOWER(TRIM(cities.name)) = LOWER(?)', [$t]);
                    })
                    ->pluck('id')
                    ->all();
            }

            $query->where(function (Builder $q) use ($like, $cityIdMatches) {
                $q->whereRaw('LOWER(name) LIKE LOWER(?)', [$like])
                    ->orWhereRaw('LOWER(address) LIKE LOWER(?)', [$like]);
                if (Schema::hasColumn('hotels', 'city')) {
                    $q->orWhereRaw('LOWER(city) LIKE LOWER(?)', [$like]);
                }
                if (Schema::hasColumn('hotels', 'city_id') && Schema::hasTable('cities')) {
                    if ($cityIdMatches !== []) {
                        $q->orWhereIn('city_id', $cityIdMatches);
                    }
                    $q->orWhereExists(function ($sub) use ($like) {
                        $sub->selectRaw('1')
                            ->from('cities')
                            ->whereColumn('cities.id', 'hotels.city_id')
                            ->whereRaw('LOWER(cities.name) LIKE LOWER(?)', [$like]);
                    });
                }
            });
        }

        $hotels = $query->orderBy('name')->limit(50)->get();
        $favouriteSet = array_flip($favouriteIds);

        $items = [];
        foreach ($hotels as $hotel) {
            $items[] = $this->presentHotel($hotel, isset($favouriteSet[(int) $hotel->id]));
        }

        // Favourites first so highlighted items are easy to spot.
        usort($items, function (array $a, array $b) {
            if ($a['is_favourite'] !== $b['is_favourite']) {
                return $a['is_favourite'] ? -1 : 1;
            }

            return ($a['price_per_night'] <=> $b['price_per_night'])
                ?: strcmp((string) $a['name'], (string) $b['name']);
        });

        return $items;
    }

    /**
     * @return array<string, mixed>
     */
    private function presentHotel(Hotel $hotel, bool $isFavourite): array
    {
        $city = $this->resolveCityLabel($hotel);
        $photo = $this->firstHotelPhotoUrl((int) $hotel->id);
        $bounds = $this->roomPriceBounds((int) $hotel->id);
        $amenities = array_slice($this->facilityNamesForHotel((int) $hotel->id), 0, 8);
        $rating = (float) ($hotel->getAttribute('aggregate_rating')
            ?? $hotel->getAttribute('rating')
            ?? $hotel->getAttribute('star_rating')
            ?? 0);

        return [
            'id' => (int) $hotel->id,
            'hotel_id' => (int) $hotel->id,
            'name' => (string) $hotel->name,
            'location' => $city !== '' ? $city : (string) ($hotel->address ?? ''),
            'city' => $city,
            'address' => (string) ($hotel->address ?? ''),
            'photo' => $photo,
            'stars' => $rating,
            'rating' => $rating,
            'price_per_night' => $bounds['min'],
            'price_per_night_max' => $bounds['max'],
            'currency' => 'BDT',
            'amenities' => $amenities,
            'is_favourite' => $isFavourite,
        ];
    }

    private function resolveCityLabel(Hotel $hotel): string
    {
        if (Schema::hasColumn('hotels', 'city')) {
            $city = trim((string) ($hotel->getAttribute('city') ?? ''));
            if ($city !== '') {
                return $city;
            }
        }

        if (Schema::hasColumn('hotels', 'city_id') && Schema::hasTable('cities')) {
            $cid = $hotel->getAttribute('city_id');
            if ($cid !== null && $cid !== '') {
                $name = DB::table('cities')->where('id', $cid)->value('name');
                if ($name !== null && trim((string) $name) !== '') {
                    return trim((string) $name);
                }
            }
        }

        return '';
    }

    private function firstHotelPhotoUrl(int $hotelId): ?string
    {
        if (Schema::hasTable('hotel_images')) {
            if (Schema::hasColumn('hotel_images', 'type')) {
                $cover = DB::table('hotel_images')
                    ->where('hotel_id', $hotelId)
                    ->whereNotNull('type')
                    ->whereRaw("LOWER(TRIM(type)) IN ('cover', 'hero', 'header')")
                    ->orderBy('sort_order')
                    ->orderBy('id')
                    ->value('image_path');
                $url = $this->imageUrl($cover !== null ? (string) $cover : null);
                if ($url) {
                    return $url;
                }
            }
            $any = DB::table('hotel_images')
                ->where('hotel_id', $hotelId)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->value('image_path');
            $url = $this->imageUrl($any !== null ? (string) $any : null);
            if ($url) {
                return $url;
            }
        }

        if (Schema::hasTable('hotel_photos')) {
            $legacy = DB::table('hotel_photos')
                ->where('hotel_id', $hotelId)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->value('url');
            $url = $this->imageUrl($legacy !== null ? (string) $legacy : null);
            if ($url) {
                return $url;
            }
        }

        return null;
    }

    /**
     * @return array{min: float, max: float}
     */
    private function roomPriceBounds(int $hotelId): array
    {
        if (Schema::hasTable('hotel_rooms') && Schema::hasColumn('hotel_rooms', 'base_price')) {
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
                $min = (float) $row->__min;
                $max = $row->__max !== null ? (float) $row->__max : $min;

                return ['min' => $min, 'max' => $max];
            }
        }

        return ['min' => 0.0, 'max' => 0.0];
    }

    /**
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
     * @return list<array{url: string, type: string}>
     */
    private function hotelGallery(int $hotelId): array
    {
        $photos = [];
        if (Schema::hasTable('hotel_images')) {
            $rows = DB::table('hotel_images')
                ->where('hotel_id', $hotelId)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get(['image_path', 'type']);
            foreach ($rows as $row) {
                $url = $this->imageUrl((string) ($row->image_path ?? ''));
                if ($url) {
                    $photos[] = [
                        'url' => $url,
                        'type' => (string) ($row->type ?? 'gallery'),
                    ];
                }
            }
        }
        if ($photos === [] && Schema::hasTable('hotel_photos')) {
            $rows = DB::table('hotel_photos')
                ->where('hotel_id', $hotelId)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get(['url']);
            foreach ($rows as $row) {
                $url = $this->imageUrl((string) ($row->url ?? ''));
                if ($url) {
                    $photos[] = ['url' => $url, 'type' => 'gallery'];
                }
            }
        }

        return $photos;
    }

    /**
     * @return array{language: string, short: string, long: string}|null
     */
    private function hotelDescription(Hotel $hotel): ?array
    {
        if (Schema::hasTable('hotel_descriptions')) {
            $rows = DB::table('hotel_descriptions')->where('hotel_id', $hotel->id)->get();
            $description = null;
            foreach ($rows as $desc) {
                $lang = strtolower((string) ($desc->language ?? 'en'));
                if ($description === null || $lang === 'en' || $lang === 'bn') {
                    $description = [
                        'language' => $lang,
                        'short' => (string) ($desc->short_description ?? ''),
                        'long' => (string) ($desc->long_description ?? ''),
                    ];
                    if ($lang === 'en') {
                        break;
                    }
                }
            }
            if ($description !== null) {
                return $description;
            }
        }

        $text = trim((string) ($hotel->getAttribute('description') ?? ''));
        if ($text === '') {
            return null;
        }

        return [
            'language' => 'en',
            'short' => $text,
            'long' => $text,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function activeRoomsForHotel(int $hotelId): array
    {
        if (! Schema::hasTable('hotel_rooms')) {
            return [];
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

        $rooms = [];
        foreach ($q->orderBy('id')->get() as $room) {
            $rooms[] = [
                'id' => (int) $room->id,
                'name' => (string) ($room->name ?? 'Room'),
                'room_type' => (string) ($room->room_type_name ?? $room->name ?? ''),
                'max_adults' => (int) ($room->max_adults ?? 0),
                'max_children' => (int) ($room->max_children ?? 0),
                'max_occupancy' => (int) ($room->max_occupancy ?? 0),
                'base_price' => (float) ($room->base_price ?? 0),
                'currency' => 'BDT',
            ];
        }

        return $rooms;
    }

    /**
     * @return list<array{type: string, text: string}>
     */
    private function hotelPolicies(Hotel $hotel): array
    {
        if (Schema::hasTable('hotel_policies')) {
            $out = [];
            foreach (DB::table('hotel_policies')->where('hotel_id', $hotel->id)->get() as $policy) {
                $out[] = [
                    'type' => (string) ($policy->policy_type ?? ''),
                    'text' => (string) ($policy->policy_text ?? ''),
                ];
            }
            if ($out !== []) {
                return $out;
            }
        }

        $text = trim((string) ($hotel->getAttribute('policies') ?? ''));
        if ($text === '') {
            return [];
        }

        return [['type' => 'general', 'text' => $text]];
    }

    /**
     * @return array{phone: mixed, email: mixed, website: mixed}
     */
    private function hotelContact(int $hotelId): array
    {
        if (Schema::hasTable('hotel_contacts')) {
            $row = DB::table('hotel_contacts')->where('hotel_id', $hotelId)->first();
            if ($row) {
                return [
                    'phone' => $row->phone ?? null,
                    'email' => $row->email ?? null,
                    'website' => $row->website ?? null,
                ];
            }
        }

        return ['phone' => null, 'email' => null, 'website' => null];
    }

    /**
     * @return array{latitude: mixed, longitude: mixed, landmark: mixed}
     */
    private function hotelGeo(Hotel $hotel): array
    {
        if (Schema::hasTable('hotel_locations')) {
            $row = DB::table('hotel_locations')->where('hotel_id', $hotel->id)->first();
            if ($row) {
                return [
                    'latitude' => $row->latitude ?? null,
                    'longitude' => $row->longitude ?? null,
                    'landmark' => $row->landmark ?? null,
                ];
            }
        }

        return [
            'latitude' => $hotel->getAttribute('lat'),
            'longitude' => $hotel->getAttribute('lng'),
            'landmark' => null,
        ];
    }
}
