<?php

namespace App\Http\Controllers\Api\v1\Merchant;

use App\Http\Controllers\Controller;
use App\Models\DeckFare;
use App\Models\RouteProperty;
use App\Models\Vehicle;
use App\Models\VehicleRoute;
use App\Support\ResolvesMerchantOwner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Via-stop / deck fare plans scoped by route + vehicle type.
 * Plan rows use vehicle_id = null and are synced onto matching vehicles.
 */
class MerchantFareController extends Controller
{
    use ResolvesMerchantOwner;

    public function index(Request $request, int $routeId): JsonResponse
    {
        $ownerId = $this->merchantOwnerId($request);
        $route = $this->ownedRoute($routeId);
        $vehicleType = strtolower((string) $request->get('vehicle_type', $route->service_type ?: 'bus'));

        $stops = $this->orderedStops($routeId);
        $plans = DeckFare::query()
            ->with(['departureFrom.ghat', 'departureTo.ghat'])
            ->where('route_id', $routeId)
            ->where('merchant_id', $ownerId)
            ->whereNull('vehicle_id')
            ->where(function ($q) use ($vehicleType) {
                $q->where('vehicle_type', $vehicleType)->orWhereNull('vehicle_type');
            })
            ->orderBy('id')
            ->get();

        $matrix = [];
        foreach ($plans as $fare) {
            $fromId = (string) $fare->departure_from;
            $toId = (string) $fare->departure_to;
            $matrix[$fromId][$toId] = $this->serializeFare($fare);
        }

        $activeCount = $plans->where('is_active', true)->count();
        $lastUpdated = optional($plans->max('updated_at'));

        return response()->json([
            'success' => true,
            'data' => [
                'route' => [
                    'id' => (string) $route->id,
                    'route_name' => $route->route_name,
                    'route_no' => $route->route_no,
                    'service_type' => $route->service_type,
                    'route_type' => $route->route_type,
                ],
                'vehicle_type' => $vehicleType,
                'stops' => $stops,
                'fares' => $plans->map(fn (DeckFare $fare) => $this->serializeFare($fare))->values(),
                'matrix' => $matrix,
                'stats' => [
                    'total_stops' => count($stops),
                    'total_fare_rules' => $plans->count(),
                    'active_rules' => $activeCount,
                    'last_updated' => $lastUpdated ? $lastUpdated->toIso8601String() : null,
                ],
            ],
        ]);
    }

    public function store(Request $request, int $routeId): JsonResponse
    {
        $ownerId = $this->merchantOwnerId($request);
        $route = $this->ownedRoute($routeId);
        $payload = $this->validatedFarePayload($request, $routeId);

        if ((int) $payload['departure_from'] === (int) $payload['departure_to']) {
            throw ValidationException::withMessages([
                'departure_to' => 'Boarding and dropping stops must be different.',
            ]);
        }

        if ($payload['fare'] <= 0) {
            throw ValidationException::withMessages([
                'fare' => 'Seat price must be greater than zero.',
            ]);
        }

        $vehicleType = strtolower($payload['vehicle_type'] ?? ($route->service_type ?: 'bus'));

        $duplicate = DeckFare::query()
            ->where('route_id', $routeId)
            ->where('merchant_id', $ownerId)
            ->whereNull('vehicle_id')
            ->where('vehicle_type', $vehicleType)
            ->where('departure_from', $payload['departure_from'])
            ->where('departure_to', $payload['departure_to'])
            ->when(! empty($payload['id']), fn ($q) => $q->where('id', '!=', $payload['id']))
            ->exists();

        if ($duplicate) {
            throw ValidationException::withMessages([
                'departure_to' => 'A fare already exists for this stop pair and vehicle type.',
            ]);
        }

        $fare = DB::transaction(function () use ($ownerId, $routeId, $vehicleType, $payload, $request) {
            $plan = ! empty($payload['id'])
                ? DeckFare::query()
                    ->where('merchant_id', $ownerId)
                    ->whereNull('vehicle_id')
                    ->findOrFail($payload['id'])
                : DeckFare::query()->firstOrNew([
                    'route_id' => $routeId,
                    'merchant_id' => $ownerId,
                    'vehicle_id' => null,
                    'vehicle_type' => $vehicleType,
                    'departure_from' => $payload['departure_from'],
                    'departure_to' => $payload['departure_to'],
                ]);

            $this->fillFare($plan, $ownerId, $routeId, null, $vehicleType, $payload, $request);
            $plan->save();

            $this->syncPlanToVehicles($plan, $ownerId, $vehicleType, $payload, $request);

            return $plan->fresh(['departureFrom.ghat', 'departureTo.ghat']);
        });

        return response()->json([
            'success' => true,
            'message' => 'Fare saved and applied to matching vehicles.',
            'data' => $this->serializeFare($fare),
        ], ! empty($payload['id']) ? 200 : 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $ownerId = $this->merchantOwnerId($request);
        $plan = DeckFare::query()
            ->where('merchant_id', $ownerId)
            ->whereNull('vehicle_id')
            ->findOrFail($id);

        $request->merge([
            'id' => $plan->id,
            'departure_from' => $request->input('departure_from', $plan->departure_from),
            'departure_to' => $request->input('departure_to', $plan->departure_to),
            'vehicle_type' => $request->input('vehicle_type', $plan->vehicle_type),
            'fare' => $request->input('fare', $plan->fare),
        ]);

        return $this->store($request, (int) $plan->route_id);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $ownerId = $this->merchantOwnerId($request);
        $plan = DeckFare::query()
            ->where('merchant_id', $ownerId)
            ->whereNull('vehicle_id')
            ->findOrFail($id);

        DB::transaction(function () use ($plan, $ownerId) {
            DeckFare::query()
                ->where('merchant_id', $ownerId)
                ->where('route_id', $plan->route_id)
                ->where('vehicle_type', $plan->vehicle_type)
                ->where('departure_from', $plan->departure_from)
                ->where('departure_to', $plan->departure_to)
                ->whereNotNull('vehicle_id')
                ->delete();

            $plan->delete();
        });

        return response()->json([
            'success' => true,
            'message' => 'Fare deleted.',
        ]);
    }

    public function bulk(Request $request, int $routeId): JsonResponse
    {
        $ownerId = $this->merchantOwnerId($request);
        $this->ownedRoute($routeId);

        $validated = $request->validate([
            'vehicle_type' => 'required|string|max:32',
            'action' => 'required|string|in:increase_percent,decrease_percent,set_fixed,apply_percent',
            'value' => 'required|numeric',
            'fare_ids' => 'nullable|array',
            'fare_ids.*' => 'integer',
        ]);

        $vehicleType = strtolower($validated['vehicle_type']);
        $query = DeckFare::query()
            ->where('route_id', $routeId)
            ->where('merchant_id', $ownerId)
            ->whereNull('vehicle_id')
            ->where('vehicle_type', $vehicleType);

        if (! empty($validated['fare_ids'])) {
            $query->whereIn('id', $validated['fare_ids']);
        }

        $plans = $query->get();
        $value = (float) $validated['value'];
        $updated = 0;

        DB::transaction(function () use ($plans, $validated, $value, $ownerId, $request, &$updated) {
            foreach ($plans as $plan) {
                $fare = (float) $plan->fare;
                $next = match ($validated['action']) {
                    'increase_percent', 'apply_percent' => $fare * (1 + ($value / 100)),
                    'decrease_percent' => max(0, $fare * (1 - ($value / 100))),
                    'set_fixed' => $value,
                    default => $fare,
                };
                $next = round(max(0, $next), 2);
                if ($next <= 0) {
                    continue;
                }

                $payload = [
                    'departure_from' => $plan->departure_from,
                    'departure_to' => $plan->departure_to,
                    'fare' => $next,
                    'reverse_fare' => $plan->reverse_fare,
                    'is_active' => $plan->is_active,
                    'notes' => $plan->notes,
                    'meta' => is_array($plan->meta) ? $plan->meta : (json_decode((string) $plan->meta, true) ?: []),
                ];

                $this->fillFare($plan, $ownerId, (int) $plan->route_id, null, (string) $plan->vehicle_type, $payload, $request);
                $plan->save();
                $this->syncPlanToVehicles($plan, $ownerId, (string) $plan->vehicle_type, $payload, $request);
                $updated++;
            }
        });

        return response()->json([
            'success' => true,
            'message' => "Updated {$updated} fare rule(s).",
            'data' => ['updated' => $updated],
        ]);
    }

    private function ownedRoute(int $routeId): VehicleRoute
    {
        return VehicleRoute::query()->findOrFail($routeId);
    }

    /** @return list<array{id:string,name:string,type:string,sequence:int}> */
    private function orderedStops(int $routeId): array
    {
        return RouteProperty::query()
            ->with('ghat')
            ->where('route_id', $routeId)
            ->orderBy('serial_num')
            ->get()
            ->map(fn (RouteProperty $stop) => [
                'id' => (string) $stop->id,
                'name' => (string) ($stop->ghat?->name ?: $stop->name ?: 'Stop'),
                'type' => (string) ($stop->type ?: 'via'),
                'sequence' => (int) ($stop->serial_num ?: 0),
            ])
            ->values()
            ->all();
    }

    /** @return array<string, mixed> */
    private function validatedFarePayload(Request $request, int $routeId): array
    {
        return $request->validate([
            'id' => 'nullable|integer|exists:deck_fares,id',
            'vehicle_type' => 'nullable|string|max:32',
            'departure_from' => [
                'required',
                'integer',
                Rule::exists('route_properties', 'id')->where(fn ($q) => $q->where('route_id', $routeId)),
            ],
            'departure_to' => [
                'required',
                'integer',
                Rule::exists('route_properties', 'id')->where(fn ($q) => $q->where('route_id', $routeId)),
            ],
            'fare' => 'required|numeric|min:0.01',
            'reverse_fare' => 'nullable|numeric|min:0',
            'is_active' => 'nullable|boolean',
            'notes' => 'nullable|string|max:2000',
            'meta' => 'nullable|array',
            'meta.cabin_price' => 'nullable|numeric|min:0',
            'meta.child_price' => 'nullable|numeric|min:0',
            'meta.infant_price' => 'nullable|numeric|min:0',
            'meta.vip_price' => 'nullable|numeric|min:0',
            'meta.ladies_price' => 'nullable|numeric|min:0',
            'meta.round_trip_price' => 'nullable|numeric|min:0',
            'meta.senior_discount_percent' => 'nullable|numeric|min:0|max:100',
            'meta.student_discount_percent' => 'nullable|numeric|min:0|max:100',
            'meta.agent_commission_percent' => 'nullable|numeric|min:0|max:100',
            'meta.platform_commission_percent' => 'nullable|numeric|min:0|max:100',
            'meta.tax_percent' => 'nullable|numeric|min:0|max:100',
            'meta.tax_included' => 'nullable|boolean',
            'meta.price_type' => 'nullable|string|in:per_seat,per_cabin',
            'meta.vehicle_class' => 'nullable|string|max:64',
            'meta.seat_type' => 'nullable|string|max:64',
            'meta.apply_for_days' => 'nullable|string|max:64',
            'meta.weekend_price_enabled' => 'nullable|boolean',
            'meta.weekend_price_percent' => 'nullable|numeric',
            'meta.holiday_price_enabled' => 'nullable|boolean',
            'meta.holiday_price_percent' => 'nullable|numeric',
            'meta.peak_hour_price_enabled' => 'nullable|boolean',
            'meta.peak_hour_price_percent' => 'nullable|numeric',
            'meta.effective_from' => 'nullable|date',
            'meta.effective_to' => 'nullable|date',
            'meta.priority' => 'nullable|integer|min:1',
            'meta.distance_km' => 'nullable|numeric|min:0',
            'meta.estimated_time' => 'nullable|string|max:64',
        ]);
    }

    /** @param array<string, mixed> $payload */
    private function fillFare(
        DeckFare $fare,
        int $ownerId,
        int $routeId,
        ?int $vehicleId,
        string $vehicleType,
        array $payload,
        Request $request
    ): void {
        $fare->route_id = $routeId;
        $fare->merchant_id = $ownerId;
        $fare->vehicle_id = $vehicleId;
        $fare->vehicle_type = $vehicleType;
        $fare->departure_from = $payload['departure_from'];
        $fare->departure_to = $payload['departure_to'];
        $fare->fare = abs((float) $payload['fare']);
        $fare->reverse_fare = abs((float) ($payload['reverse_fare'] ?? $payload['fare']));
        $fare->is_active = array_key_exists('is_active', $payload) ? (bool) $payload['is_active'] : true;
        $fare->notes = $payload['notes'] ?? null;
        $fare->meta = $payload['meta'] ?? [];
        $fare->user_id = optional($request->user())->id ?? $ownerId;
        $fare->type = $fare->type ?: 'straight';
    }

    /** @param array<string, mixed> $payload */
    private function syncPlanToVehicles(
        DeckFare $plan,
        int $ownerId,
        string $vehicleType,
        array $payload,
        Request $request
    ): void {
        $vehicles = Vehicle::query()
            ->where('merchant_id', $ownerId)
            ->where('route_id', $plan->route_id)
            ->where('vehicle_type', $vehicleType)
            ->get(['id']);

        foreach ($vehicles as $vehicle) {
            $row = DeckFare::query()->firstOrNew([
                'route_id' => $plan->route_id,
                'merchant_id' => $ownerId,
                'vehicle_id' => $vehicle->id,
                'departure_from' => $plan->departure_from,
                'departure_to' => $plan->departure_to,
            ]);
            $this->fillFare($row, $ownerId, (int) $plan->route_id, (int) $vehicle->id, $vehicleType, $payload, $request);
            $row->save();
        }
    }

    /** @return array<string, mixed> */
    private function serializeFare(DeckFare $fare): array
    {
        $meta = $fare->meta;
        if (is_string($meta)) {
            $meta = json_decode($meta, true) ?: [];
        }
        if (! is_array($meta)) {
            $meta = [];
        }

        return [
            'id' => (string) $fare->id,
            'route_id' => (string) $fare->route_id,
            'vehicle_id' => $fare->vehicle_id !== null ? (string) $fare->vehicle_id : null,
            'vehicle_type' => $fare->vehicle_type,
            'boarding_stop_id' => (string) $fare->departure_from,
            'dropping_stop_id' => (string) $fare->departure_to,
            'boarding_stop' => (string) ($fare->departureFrom?->ghat?->name ?: $fare->departureFrom?->name ?: ''),
            'dropping_stop' => (string) ($fare->departureTo?->ghat?->name ?: $fare->departureTo?->name ?: ''),
            'fare' => (float) $fare->fare,
            'reverse_fare' => (float) ($fare->reverse_fare ?? $fare->fare),
            'is_active' => (bool) ($fare->is_active ?? true),
            'notes' => $fare->notes,
            'meta' => $meta,
            'updated_at' => optional($fare->updated_at)?->toIso8601String(),
            'created_at' => optional($fare->created_at)?->toIso8601String(),
        ];
    }
}
