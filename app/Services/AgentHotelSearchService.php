<?php

namespace App\Services;

use App\Models\Agent;
use App\Models\AgentHotelFavorite;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
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
        $hotel = $hotel
            ->with([
                'city',
                'images',
                'facilities',
                'descriptions',
                'policies',
                'contact',
                'location',
                'activeRooms.roomType',
            ])
            ->find($hotelId);

        if (! $hotel) {
            throw new \InvalidArgumentException(__('Hotel not found'));
        }

        $favouriteIds = $this->favouriteMeta($agent)['favourite_hotel_ids'];
        $isFavourite = in_array($hotelId, $favouriteIds, true);
        $card = $this->presentHotel($hotel, $isFavourite);

        $photos = [];
        foreach ($hotel->images ?? [] as $image) {
            $url = $this->imageUrl($image->image_path ?? null);
            if ($url) {
                $photos[] = [
                    'url' => $url,
                    'type' => (string) ($image->type ?? 'gallery'),
                ];
            }
        }
        if ($photos === [] && ! empty($card['photo'])) {
            $photos[] = ['url' => $card['photo'], 'type' => 'cover'];
        }

        $description = null;
        foreach ($hotel->descriptions ?? [] as $desc) {
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

        $rooms = [];
        foreach ($hotel->activeRooms ?? [] as $room) {
            $rooms[] = [
                'id' => (int) $room->id,
                'name' => (string) ($room->name ?? $room->roomType?->name ?? 'Room'),
                'room_type' => (string) ($room->roomType?->name ?? ''),
                'max_adults' => (int) ($room->max_adults ?? 0),
                'max_children' => (int) ($room->max_children ?? 0),
                'max_occupancy' => (int) ($room->max_occupancy ?? 0),
                'base_price' => (float) ($room->base_price ?? 0),
                'currency' => 'BDT',
            ];
        }

        $policies = [];
        foreach ($hotel->policies ?? [] as $policy) {
            $policies[] = [
                'type' => (string) ($policy->policy_type ?? ''),
                'text' => (string) ($policy->policy_text ?? ''),
            ];
        }

        $amenities = $hotel->facilities
            ? $hotel->facilities->pluck('name')->filter()->values()->all()
            : [];

        return array_merge($card, [
            'photos' => $photos,
            'amenities' => $amenities,
            'description' => $description,
            'check_in_time' => $hotel->check_in_time ? (string) $hotel->check_in_time : null,
            'check_out_time' => $hotel->check_out_time ? (string) $hotel->check_out_time : null,
            'contact' => [
                'phone' => $hotel->contact?->phone,
                'email' => $hotel->contact?->email,
                'website' => $hotel->contact?->website,
            ],
            'geo' => [
                'latitude' => $hotel->location?->latitude,
                'longitude' => $hotel->location?->longitude,
                'landmark' => $hotel->location?->landmark,
            ],
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
            ->whereIn('id', $favouriteIds)
            ->with(['city', 'images', 'facilities', 'activeRooms']);
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
            ->where('status', 1)
            ->with(['city', 'images', 'facilities', 'activeRooms']);
        \App\Support\PublicListingVisibility::applyApprovedHotel($query);

        if ($city !== '') {
            $like = '%'.$city.'%';
            $query->where(function (Builder $q) use ($like, $city) {
                $q->where('name', 'like', $like)
                    ->orWhere('address', 'like', $like);
                if (Schema::hasColumn('hotels', 'city')) {
                    $q->orWhere('city', 'like', $like);
                }
                if (Schema::hasColumn('hotels', 'city_id') && Schema::hasTable('cities')) {
                    $q->orWhereHas('city', function (Builder $c) use ($like, $city) {
                        $c->where('name', 'like', $like)
                            ->orWhereRaw('LOWER(TRIM(name)) = LOWER(?)', [trim($city)]);
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
        $city = $hotel->city?->name
            ?? (Schema::hasColumn('hotels', 'city') ? (string) ($hotel->getAttribute('city') ?? '') : '');
        $photo = null;
        $image = $hotel->images?->first();
        if ($image && ! empty($image->image_path)) {
            $photo = $this->imageUrl((string) $image->image_path);
        }

        $prices = $hotel->activeRooms
            ? $hotel->activeRooms->pluck('base_price')->filter(fn ($p) => $p !== null)->map(fn ($p) => (float) $p)
            : collect();
        $min = $prices->isNotEmpty() ? (float) $prices->min() : 0.0;
        $max = $prices->isNotEmpty() ? (float) $prices->max() : $min;

        $amenities = $hotel->facilities
            ? $hotel->facilities->pluck('name')->filter()->values()->take(8)->all()
            : [];

        return [
            'id' => (int) $hotel->id,
            'hotel_id' => (int) $hotel->id,
            'name' => (string) $hotel->name,
            'location' => $city !== '' ? $city : (string) ($hotel->address ?? ''),
            'city' => $city,
            'address' => (string) ($hotel->address ?? ''),
            'photo' => $photo,
            'stars' => (float) ($hotel->rating ?? 0),
            'rating' => (float) ($hotel->rating ?? 0),
            'price_per_night' => $min,
            'price_per_night_max' => $max,
            'currency' => 'BDT',
            'amenities' => $amenities,
            'is_favourite' => $isFavourite,
        ];
    }
}
