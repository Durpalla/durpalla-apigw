<?php

namespace App\Services;

use App\Constants\AppConst;
use App\Models\Agent;
use App\Models\AgentVehicleFavorite;
use App\Models\Vehicle;
use App\Models\VehicleSchedule;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class AgentUpcomingTripService
{
    /**
     * @return array{has_favourites:bool,favourite_vehicle_ids:list<int>}
     */
    public function favouriteMeta(Agent $agent): array
    {
        $ids = AgentVehicleFavorite::query()
            ->where('agent_id', $agent->id)
            ->orderByDesc('id')
            ->pluck('vehicle_id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        return [
            'has_favourites' => $ids !== [],
            'favourite_vehicle_ids' => $ids,
        ];
    }

    /**
     * @param  array{departure?:string,destination?:string,vehicle_type?:string|array,favourites_only?:bool|string|int,page?:int,size?:int}  $filters
     */
    public function upcoming(Agent $agent, array $filters = []): LengthAwarePaginator
    {
        $meta = $this->favouriteMeta($agent);
        $favouritesOnly = array_key_exists('favourites_only', $filters)
            ? filter_var($filters['favourites_only'], FILTER_VALIDATE_BOOLEAN)
            : $meta['has_favourites'];

        $types = $this->normalizeTypes($filters['vehicle_type'] ?? null);
        $departure = trim((string) ($filters['departure'] ?? ''));
        $destination = trim((string) ($filters['destination'] ?? ''));
        $page = max(1, (int) ($filters['page'] ?? 1));
        $size = max(1, min(50, (int) ($filters['size'] ?? 20)));

        $query = VehicleSchedule::query()
            ->with([
                'vehicle:id,name,vehicle_type,photo',
                'route:id,route_name',
                'startFrom:id,name',
                'stopTo:id,name',
                'startingPoint.ghat:id,name',
                'endingPoint.ghat:id,name',
            ])
            ->where('status', AppConst::SCHEDULE_ACTIVE)
            ->where(function (Builder $q) {
                $q->where('leaving_at', '>=', now()->toDateTimeString())
                    ->orWhere(function (Builder $inner) {
                        $inner->whereNull('leaving_at')
                            ->whereDate('schedule_date', '>=', now()->toDateString());
                    });
            });

        if ($favouritesOnly) {
            if ($meta['favourite_vehicle_ids'] === []) {
                $query->whereRaw('1 = 0');
            } else {
                $query->whereIn('vehicle_id', $meta['favourite_vehicle_ids']);
            }
        }

        if ($types !== []) {
            $query->whereHas('vehicle', function (Builder $q) use ($types) {
                $q->whereIn('vehicle_type', $types);
            });
        }

        if ($departure !== '') {
            $like = '%'.$departure.'%';
            $query->where(function (Builder $q) use ($like) {
                $q->whereHas('startFrom', fn (Builder $g) => $g->where('name', 'like', $like))
                    ->orWhereHas('startingPoint.ghat', fn (Builder $g) => $g->where('name', 'like', $like));
            });
        }

        if ($destination !== '') {
            $like = '%'.$destination.'%';
            $query->where(function (Builder $q) use ($like) {
                $q->whereHas('stopTo', fn (Builder $g) => $g->where('name', 'like', $like))
                    ->orWhereHas('endingPoint.ghat', fn (Builder $g) => $g->where('name', 'like', $like));
            });
        }

        return $query
            ->orderBy('schedule_date')
            ->orderBy('leaving_at')
            ->paginate($size, ['*'], 'page', $page)
            ->through(fn (VehicleSchedule $trip) => $this->presentTrip(
                $trip,
                in_array((int) $trip->vehicle_id, $meta['favourite_vehicle_ids'], true)
            ));
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function listFavourites(Agent $agent): Collection
    {
        return AgentVehicleFavorite::query()
            ->where('agent_id', $agent->id)
            ->with(['vehicle:id,name,vehicle_type,photo,status'])
            ->orderByDesc('id')
            ->get()
            ->map(function (AgentVehicleFavorite $row) {
                $vehicle = $row->vehicle;

                return [
                    'id' => (int) $row->id,
                    'vehicle_id' => (int) $row->vehicle_id,
                    'vehicle_name' => $vehicle?->name,
                    'vehicle_type' => $vehicle?->vehicle_type,
                    'photo' => $vehicle?->photo,
                    'favorited_at' => optional($row->created_at)?->toIso8601String(),
                ];
            })
            ->values();
    }

    public function addFavourite(Agent $agent, int $vehicleId): array
    {
        $vehicle = Vehicle::query()->find($vehicleId);
        if (! $vehicle) {
            throw new \InvalidArgumentException(__('Vehicle not found'));
        }

        $favorite = AgentVehicleFavorite::query()->firstOrCreate([
            'agent_id' => $agent->id,
            'vehicle_id' => $vehicleId,
        ]);

        return [
            'vehicle_id' => (int) $vehicleId,
            'vehicle_name' => $vehicle->name,
            'vehicle_type' => $vehicle->vehicle_type,
            'is_favourite' => true,
            'created' => $favorite->wasRecentlyCreated,
        ];
    }

    public function removeFavourite(Agent $agent, int $vehicleId): bool
    {
        return AgentVehicleFavorite::query()
            ->where('agent_id', $agent->id)
            ->where('vehicle_id', $vehicleId)
            ->delete() > 0;
    }

    /**
     * @return list<string>
     */
    private function normalizeTypes(mixed $raw): array
    {
        if ($raw === null || $raw === '') {
            return [];
        }

        $values = is_array($raw) ? $raw : explode(',', (string) $raw);
        $allowed = ['bus', 'launch', 'train', 'air'];
        $normalized = [];
        foreach ($values as $value) {
            $type = strtolower(trim((string) $value));
            if ($type === 'flight') {
                $type = 'air';
            }
            if ($type === 'boat') {
                $type = 'launch';
            }
            if (in_array($type, $allowed, true)) {
                $normalized[] = $type;
            }
        }

        return array_values(array_unique($normalized));
    }

    /**
     * @return array<string, mixed>
     */
    private function presentTrip(VehicleSchedule $trip, bool $isFavourite): array
    {
        $vehicle = $trip->vehicle;
        $departure = $trip->startFrom?->name
            ?? $trip->startingPoint?->ghat?->name
            ?? '';
        $destination = $trip->stopTo?->name
            ?? $trip->endingPoint?->ghat?->name
            ?? '';

        return [
            'trip_id' => (int) $trip->id,
            'vehicle_id' => (int) $trip->vehicle_id,
            'vehicle_name' => $vehicle?->name,
            'vehicle_type' => $vehicle?->vehicle_type,
            'route_name' => $trip->route?->route_name
                ?: trim($departure.($departure && $destination ? ' → ' : '').$destination),
            'departure' => $departure,
            'destination' => $destination,
            'schedule_date' => $trip->schedule_date,
            'leaving_at' => $trip->leaving_at,
            'is_favourite' => $isFavourite,
        ];
    }
}
