<?php

namespace App\Services\Transport;

use App\Models\Ghat;
use App\Models\RouteProperty;
use App\Models\VehicleSchedule;
use App\Services\Transport\DTO\TransportSearchRequestDTO;
use Illuminate\Http\Request;

class TransportApiService
{
    public function __construct(
        protected TransportSearchService $searchService
    ) {}

    /**
     * Normalize search request: accept from_location_id/to_location_id OR trip_from/trip_to (ghat names).
     */
    public function normalizeSearchRequest(Request $request): TransportSearchRequestDTO
    {
        $fromId = $request->integer('from_location_id') ?: null;
        $toId = $request->integer('to_location_id') ?: null;

        if (!$fromId && $request->filled('trip_from')) {
            $fromId = $this->resolveGhatIdByName($request->trip_from, 'start');
        }
        if (!$toId && $request->filled('trip_to')) {
            $toId = $this->resolveGhatIdByName($request->trip_to, 'end');
        }

        $travelDate = $request->filled('travel_date')
            ? $request->travel_date
            : ($request->filled('trip_date') ? $request->trip_date : null);
        if ($travelDate) {
            $travelDate = date('Y-m-d', strtotime($travelDate));
        } else {
            $travelDate = date('Y-m-d');
        }

        $vehicleType = $request->input('vehicle_type')
            ?: $request->input('service_type')
            ?: $request->input('type');

        return new TransportSearchRequestDTO(
            fromLocationId: (int) ($fromId ?? 0),
            toLocationId: (int) ($toId ?? 0),
            travelDate: $travelDate,
            returnDate: $request->filled('trip_return_date') ? date('Y-m-d', strtotime($request->trip_return_date)) : null,
            adults: (int) ($request->input('adults', 1)),
            children: (int) ($request->input('children', 0)),
            vehicleType: $vehicleType ?: null,
            filters: array_filter([
                'max_price' => $request->input('max_price'),
                'min_price' => $request->input('min_price'),
                'departure_after' => $request->input('departure_after'),
            ])
        );
    }

    /**
     * Search trips and format for API response (unified shape).
     */
    public function search(Request $request): array
    {
        $dto = $this->normalizeSearchRequest($request);

        if ($dto->fromLocationId <= 0 || $dto->toLocationId <= 0) {
            return [];
        }

        $results = $this->searchService->search($dto);

        return array_map(function ($trip) {
            return [
                'trip_id' => $trip['schedule_id'],
                'vehicle_id' => $trip['vehicle_id'] ?? null,
                'vehicle_name' => $trip['vehicle_name'] ?? '',
                'route_name' => $trip['route_name'] ?? '',
                'leaving_at' => $trip['leaving_at'],
                'arriving_at' => $trip['arriving_at'] ?? null,
                'base_fare' => (float) ($trip['base_fare'] ?? 0),
                'available_seats' => $trip['available_seats'] ?? null,
                'available_cabins' => $trip['available_cabins'] ?? null,
                'supplier' => $trip['supplier'] ?? null,
                'transport_type' => $trip['transport_type'] ?? null,
                'book_token' => $trip['book_token'] ?? null,
            ];
        }, $results);
    }

    /**
     * Resolve ghat ID by name: prefer ghats that are actually used in routes/schedules
     * so that "Dhaka" matches the ghat used by schedules (e.g. id 3) not just first by name (e.g. id 1).
     */
    protected function resolveGhatIdByName(string $term, string $pointType): ?int
    {
        $usedIds = collect();
        if (\Schema::hasTable('route_properties')) {
            $usedIds = $usedIds->merge(
                RouteProperty::where('type', $pointType)->distinct()->pluck('ghat_id')
            );
        }
        if (\Schema::hasTable('vehicle_schedules')) {
            $col = $pointType === 'start' ? 'starting_point' : 'ending_point';
            $usedIds = $usedIds->merge(
                VehicleSchedule::distinct()->pluck($col)->filter(fn ($v) => $v !== null && $v !== '')
            );
        }
        $usedIds = $usedIds->unique()->values()->all();

        $query = Ghat::where('name', 'LIKE', $term . '%');
        if (!empty($usedIds)) {
            $used = Ghat::where('name', 'LIKE', $term . '%')->whereIn('id', $usedIds)->orderBy('id')->first();
            if ($used) {
                return $used->id;
            }
        }
        $fallback = Ghat::where('name', 'LIKE', $term . '%')->orderBy('id')->first()
            ?? Ghat::where('name', 'LIKE', '%' . $term . '%')->orderBy('id')->first();
        return $fallback?->id;
    }
}
