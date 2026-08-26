<?php

namespace App\Http\Controllers\Api\v1\Merchant;

use App\Http\Controllers\Controller;
use App\Imports\SeatCabinImport;
use App\Models\Cabin;
use App\Models\Merchant;
use App\Models\SeatLayout\Seat;
use App\Models\Service;
use App\Models\Vehicle;
use App\Models\VehicleImage;
use App\Models\VehicleRoute;
use App\Support\ResolvesMerchantOwner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Merchant Desk Pro — properties (vehicles) scoped by merchant owner id.
 */
class MerchantPropertyController extends Controller
{
    use ResolvesMerchantOwner;

    public function index(Request $request): JsonResponse
    {
        $ownerId = $this->merchantOwnerId($request);
        $merchant = Merchant::query()->find($ownerId);
        $allowed = $merchant ? $merchant->allowed_service_types : null;
        if (! is_array($allowed)) {
            $allowed = [];
        }
        $allowed = array_values(array_unique(array_filter(array_map('strval', $allowed))));

        $query = Vehicle::query()
            ->with([
                'route' => function ($q) {
                    $q->select(['id', 'route_name', 'service_type'])
                        ->with([
                            'startingPoint.ghat:id,name',
                            'endingPoint.ghat:id,name',
                            'boardingPoints' => function ($points) {
                                $points->orderBy('serial_num')->with('ghat:id,name');
                            },
                        ]);
                },
            ])
            ->where('merchant_id', $ownerId)
            ->orderByDesc('updated_at');

        // Enforce merchant's allowed service/property types (backward compatible: empty means "no restriction").
        if (count($allowed) > 0) {
            $transportAllowed = array_values(array_filter(
                $allowed,
                fn ($type) => strtolower((string) $type) !== 'hotel'
            ));
            if (count($transportAllowed) > 0) {
                $query->whereIn('vehicle_type', $transportAllowed);
            } else {
                // Hotel-only merchant: no transport vehicles.
                $query->whereRaw('1 = 0');
            }
        }

        if ($request->filled('vehicle_type') && $request->vehicle_type !== 'All Types') {
            $query->where('vehicle_type', $this->normalizeVehicleType($request->vehicle_type));
        }

        if ($request->filled('search')) {
            $s = '%'.$request->search.'%';
            $query->where(function ($q) use ($s) {
                $q->where('name', 'LIKE', $s)
                    ->orWhere('registration_no', 'LIKE', $s);
            });
        }

        $items = $query->get()->map(fn (Vehicle $v) => $this->transformVehicle($v));

        return response()->json(['success' => true, 'data' => $items]);
    }

    /**
     * Route suggestions for dropdown: merchant's existing routes first, then others.
     * Query: term (optional), service_type (optional), per_page (max 100).
     */
    public function routeSuggest(Request $request): JsonResponse
    {
        $ownerId = $this->merchantOwnerId($request);
        $term = (string) $request->get('term', '');
        $perPage = min(max((int) $request->get('per_page', 40), 1), 100);

        $usedRouteIds = Vehicle::query()
            ->where('merchant_id', $ownerId)
            ->whereNotNull('route_id')
            ->distinct()
            ->pluck('route_id')
            ->filter();

        $build = function () use ($term, $request) {
            $q = VehicleRoute::query()->select(['id', 'route_name', 'service_type']);
            if ($term !== '') {
                $q->where('route_name', 'LIKE', '%'.$term.'%');
            }
            if ($request->filled('service_type')) {
                $q->where('service_type', $request->service_type);
            }

            return $q;
        };

        $used = collect();
        if ($usedRouteIds->isNotEmpty()) {
            $used = $build()->whereIn('id', $usedRouteIds)->orderBy('route_name')->get();
        }

        $remaining = $perPage - $used->count();
        $rest = collect();
        if ($remaining > 0) {
            $q = $build();
            if ($usedRouteIds->isNotEmpty()) {
                $q->whereNotIn('id', $usedRouteIds);
            }
            $rest = $q->orderBy('route_name')->limit($remaining)->get();
        }

        $data = $used->merge($rest)->values()->map(fn (VehicleRoute $r) => [
            'id' => $r->id,
            'name' => $r->route_name,
            'service_type' => $r->service_type,
        ]);

        return response()->json(['success' => true, 'data' => $data]);
    }

    public function store(Request $request): JsonResponse
    {
        $ownerId = $this->merchantOwnerId($request);

        $validated = $request->validate($this->merchantVehicleValidationRules());

        $route = VehicleRoute::findOrFail($validated['route_id']);

        $registrationNo = isset($validated['registration_no']) && $validated['registration_no'] !== null
            ? trim((string) $validated['registration_no'])
            : '';
        if ($registrationNo === '') {
            $registrationNo = 'M-'.$ownerId.'-'.strtoupper(Str::random(12));
            while (Vehicle::where('registration_no', $registrationNo)->exists()) {
                $registrationNo = 'M-'.$ownerId.'-'.strtoupper(Str::random(12));
            }
        }

        $vehicleType = $this->resolveVehicleTypeSlug($request, $route, null, $ownerId);

        $vehicle = Vehicle::create([
            'merchant_id' => $ownerId,
            'user_id' => $request->user()->id,
            'route_id' => $route->id,
            'name' => $validated['name'],
            'registration_no' => $registrationNo,
            'engine_no' => $validated['engine_no'] ?? null,
            'vehicle_no' => $validated['vehicle_no'] ?? null,
            'registration_expiry_date' => $validated['registration_expiry_date'] ?? null,
            'fitness_expiry_date' => $validated['fitness_expiry_date'] ?? null,
            'photo' => $validated['photo'] ?? null,
            'passengers_capacity' => (int) ($validated['passengers_capacity'] ?? 0),
            'vehicle_type' => $vehicleType,
            'nid_verification_check' => $this->toBoolInt($validated['nid_verification_check'] ?? 0),
            'number_of_floor' => (int) ($validated['number_of_floor'] ?? 2),
            'ac_available' => $this->toBoolInt($validated['ac_available'] ?? 0),
            'default_tab' => (string) ($validated['default_tab'] ?? 'deck'),
            'default_floor' => (int) ($validated['default_floor'] ?? 1),
            'allowed_ticket_kinds' => $this->normalizeAllowedTicketKinds($validated['allowed_ticket_kinds'] ?? null),
            'status' => 1,
        ]);

        $vehicle->load(['route:id,route_name,service_type']);

        return response()->json([
            'success' => true,
            'message' => 'Property created.',
            'data' => $this->transformVehicle($vehicle),
        ], 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $ownerId = $this->merchantOwnerId($request);
        $vehicle = Vehicle::query()
            ->with([
                'route' => function ($q) {
                    $q->select(['id', 'route_name', 'service_type'])
                        ->with([
                            'startingPoint.ghat:id,name',
                            'endingPoint.ghat:id,name',
                            'boardingPoints' => function ($points) {
                                $points->orderBy('serial_num')->with('ghat:id,name');
                            },
                        ]);
                },
                'images',
            ])
            ->where('merchant_id', $ownerId)
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $this->transformVehicle($vehicle, withImages: true),
        ]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $ownerId = $this->merchantOwnerId($request);
        $vehicle = Vehicle::where('merchant_id', $ownerId)->findOrFail($id);

        $validated = $request->validate($this->merchantVehicleValidationRules(forUpdate: true, vehicleId: $vehicle->id));

        $routeId = (int) ($validated['route_id'] ?? $vehicle->route_id);
        $route = VehicleRoute::findOrFail($routeId);

        $vehicleType = $this->resolveVehicleTypeSlug($request, $route, $vehicle->vehicle_type, $ownerId);

        $payload = [
            'name' => $validated['name'] ?? $vehicle->name,
            'route_id' => $validated['route_id'] ?? $vehicle->route_id,
            'passengers_capacity' => array_key_exists('passengers_capacity', $validated)
                ? (int) $validated['passengers_capacity']
                : (int) $vehicle->passengers_capacity,
            'vehicle_type' => $vehicleType,
            'registration_no' => array_key_exists('registration_no', $validated)
                ? (trim((string) $validated['registration_no']) === ''
                    ? $vehicle->registration_no
                    : trim((string) $validated['registration_no']))
                : $vehicle->registration_no,
            'engine_no' => array_key_exists('engine_no', $validated) ? $validated['engine_no'] : $vehicle->engine_no,
            'vehicle_no' => array_key_exists('vehicle_no', $validated) ? $validated['vehicle_no'] : $vehicle->vehicle_no,
            'registration_expiry_date' => array_key_exists('registration_expiry_date', $validated)
                ? $validated['registration_expiry_date']
                : $vehicle->registration_expiry_date,
            'fitness_expiry_date' => array_key_exists('fitness_expiry_date', $validated)
                ? $validated['fitness_expiry_date']
                : $vehicle->fitness_expiry_date,
            'photo' => array_key_exists('photo', $validated) ? $validated['photo'] : $vehicle->photo,
            'nid_verification_check' => array_key_exists('nid_verification_check', $validated)
                ? $this->toBoolInt($validated['nid_verification_check'])
                : (int) $vehicle->nid_verification_check,
            'number_of_floor' => array_key_exists('number_of_floor', $validated)
                ? (int) $validated['number_of_floor']
                : (int) $vehicle->number_of_floor,
            'ac_available' => array_key_exists('ac_available', $validated)
                ? $this->toBoolInt($validated['ac_available'])
                : (int) $vehicle->ac_available,
            'default_tab' => array_key_exists('default_tab', $validated)
                ? (string) $validated['default_tab']
                : (string) $vehicle->default_tab,
            'default_floor' => array_key_exists('default_floor', $validated)
                ? (int) $validated['default_floor']
                : (int) $vehicle->default_floor,
            'allowed_ticket_kinds' => array_key_exists('allowed_ticket_kinds', $validated)
                ? $this->normalizeAllowedTicketKinds($validated['allowed_ticket_kinds'])
                : $vehicle->allowed_ticket_kinds,
        ];

        $vehicle->fill($payload);
        $vehicle->save();
        $vehicle->load(['route:id,route_name,service_type']);

        return response()->json([
            'success' => true,
            'message' => 'Property updated.',
            'data' => $this->transformVehicle($vehicle),
        ]);
    }

    public function updateStatus(Request $request, int $id): JsonResponse
    {
        $ownerId = $this->merchantOwnerId($request);
        $vehicle = Vehicle::where('merchant_id', $ownerId)->findOrFail($id);

        $validated = $request->validate([
            'is_active' => ['required', 'boolean'],
        ]);

        $vehicle->status = $validated['is_active'] ? 1 : 2;
        $vehicle->save();
        $vehicle->load(['route:id,route_name,service_type']);

        return response()->json([
            'success' => true,
            'message' => 'Status updated.',
            'data' => $this->transformVehicle($vehicle),
        ]);
    }

    /**
     * Layout inventory for a property: legacy cabin rows (seat/cabin/sofa) + new seat-layout seats.
     * Position editing stays in the main Durpalla dashboard (vehicle cabins); this endpoint is read-only summary for Merchant Desk Pro.
     */
    public function layoutSummary(Request $request, int $id): JsonResponse
    {
        $ownerId = $this->merchantOwnerId($request);
        $vehicle = Vehicle::where('merchant_id', $ownerId)->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $this->buildLayoutSummaryPayload($vehicle),
        ]);
    }

    /**
     * GET /merchant/properties/{id}/layout/floors
     * Distinct floors from vehicle cabins for layout preview filtering.
     */
    public function layoutFloors(Request $request, int $id): JsonResponse
    {
        $ownerId = $this->merchantOwnerId($request);
        $vehicle = Vehicle::where('merchant_id', $ownerId)->findOrFail($id);

        $floorValues = Cabin::query()
            ->where('vehicle_id', $vehicle->id)
            ->select('floor')
            ->distinct()
            ->orderBy('floor')
            ->pluck('floor')
            ->filter(fn ($f) => $f !== null && $f !== '')
            ->values()
            ->all();

        $list = collect($floorValues)->map(function ($f) {
            $floorId = (string) $f;

            return [
                'id' => $floorId,
                'label' => 'Floor '.$floorId,
            ];
        })->values()->all();

        if ($list === []) {
            $floors = max(1, (int) ($vehicle->number_of_floor ?: 1));
            for ($i = 1; $i <= $floors; $i++) {
                $list[] = ['id' => (string) $i, 'label' => 'Floor '.$i];
            }
        }

        return response()->json([
            'success' => true,
            'data' => [
                'floors' => $list,
            ],
        ]);
    }

    /**
     * GET /merchant/properties/{id}/layout/{type}?floor=1
     * Read-only seat/cabin/sofa map for Merchant Desk Pro (same row shape as trip layout).
     */
    public function layoutMap(Request $request, int $id, string $type): JsonResponse
    {
        $ownerId = $this->merchantOwnerId($request);
        $vehicle = Vehicle::where('merchant_id', $ownerId)->findOrFail($id);

        $type = strtolower(trim($type));
        if (! in_array($type, ['seat', 'cabin', 'sofa'], true)) {
            return response()->json(['success' => false, 'message' => 'Invalid layout type.'], 422);
        }

        $floor = $request->query('floor');
        $query = Cabin::query()
            ->where('vehicle_id', $vehicle->id)
            ->where('type', $type)
            ->with('cabinType')
            ->orderBy('cabin_row')
            ->orderBy('cabin_position');

        if ($floor !== null && $floor !== '') {
            $query->where('floor', $floor);
        }

        $cabins = $query->get();
        $vehicleType = strtolower(trim((string) ($vehicle->vehicle_type ?? '')));
        $useLaunchStyleLabels = in_array($vehicleType, ['launch', 'boat'], true);

        $rows = [];
        $currentRow = null;
        $rowCells = [];

        foreach ($cabins as $cabin) {
            if ($useLaunchStyleLabels) {
                $label = ($cabin->cabinType?->letter ? $cabin->cabinType->letter.'-' : '').$cabin->cabin_no;
            } else {
                $cabinNo = trim((string) ($cabin->cabin_no ?? ''));
                $label = $cabinNo !== ''
                    ? $cabinNo
                    : (($cabin->cabinType?->letter ? $cabin->cabinType->letter.'-' : '').$cabin->cabin_no);
            }

            if ($currentRow !== null && (string) $cabin->cabin_row !== (string) $currentRow) {
                $rows[] = $rowCells;
                $rowCells = [];
            }
            $currentRow = $cabin->cabin_row;

            $isReserved = (int) $cabin->is_reserved === 1;
            $rowCells[] = [
                'mapping_id' => (string) $cabin->id,
                'cabin_id' => (string) $cabin->id,
                'label' => $label,
                'state' => $isReserved ? 'blocked' : 'available',
                'status' => $isReserved ? 'blocked' : 'available',
                'floor' => $cabin->floor,
                'fare' => (float) ($cabin->fare ?? 0),
                'cabin_row' => $cabin->cabin_row,
                'cabin_position' => $cabin->cabin_position,
                'row' => (int) $cabin->cabin_row,
                'column' => (int) $cabin->cabin_position,
            ];
        }
        if (! empty($rowCells)) {
            $rows[] = $rowCells;
        }

        return response()->json([
            'success' => true,
            'data' => [
                'vehicle_id' => (string) $vehicle->id,
                'type' => $type,
                'floor' => $floor,
                'rows' => $rows,
            ],
        ]);
    }

    /**
     * POST /merchant/properties/{id}/layout/import
     *
     * Bulk import seat/cabin/sofa rows using the same {@see SeatCabinImport} as the admin vehicle cabin batch upload
     * (CSV / Excel). Column headings must match the dashboard template (WithHeadingRow).
     */
    public function importLayout(Request $request, int $id): JsonResponse
    {
        $ownerId = $this->merchantOwnerId($request);
        $vehicle = Vehicle::with(['cabins', 'seats'])->where('merchant_id', $ownerId)->findOrFail($id);

        $validated = $request->validate([
            'type' => ['required', 'string', 'in:seat,cabin,sofa'],
            'attachment' => ['required', 'file', 'max:51200', 'mimes:csv,txt,xlsx,xls,ods'],
        ]);

        if (! class_exists(\Maatwebsite\Excel\Facades\Excel::class)) {
            return response()->json([
                'success' => false,
                'message' => 'Layout import requires maatwebsite/excel. Install it on the API gateway or import via admin.',
            ], 501);
        }

        try {
            DB::transaction(function () use ($vehicle, $validated) {
                \Maatwebsite\Excel\Facades\Excel::import(
                    new SeatCabinImport($vehicle, $validated['type']),
                    $validated['attachment']
                );
            }, 3);
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Import failed: '.$e->getMessage(),
            ], 422);
        }

        $vehicle->refresh();

        return response()->json([
            'success' => true,
            'message' => 'Layout imported.',
            'data' => $this->buildLayoutSummaryPayload($vehicle),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildLayoutSummaryPayload(Vehicle $vehicle): array
    {
        $byType = Cabin::query()
            ->where('vehicle_id', $vehicle->id)
            ->selectRaw('type, COUNT(*) as c')
            ->groupBy('type')
            ->get();

        $cabins = ['seat' => 0, 'cabin' => 0, 'sofa' => 0, 'other' => 0];
        foreach ($byType as $row) {
            $raw = $row->type;
            $t = is_string($raw) && $raw !== '' ? strtolower(trim($raw)) : 'other';
            if (! in_array($t, ['seat', 'cabin', 'sofa'], true)) {
                $t = 'other';
            }
            $cabins[$t] += (int) $row->c;
        }

        $seatLayoutSeats = Seat::query()
            ->where('vehicle_id', $vehicle->id)
            ->where('is_active', true)
            ->count();

        return [
            'vehicle_id' => (string) $vehicle->id,
            'name' => $vehicle->name,
            'vehicle_type' => $vehicle->vehicle_type,
            'passengers_capacity' => (int) $vehicle->passengers_capacity,
            'number_of_floor' => $vehicle->number_of_floor !== null ? (int) $vehicle->number_of_floor : null,
            'cabins' => $cabins,
            'seat_layout_seats' => $seatLayoutSeats,
        ];
    }

    private function transformVehicle(Vehicle $v, bool $withImages = false): array
    {
        $route = $v->route;
        $boardingPoints = collect();
        if ($route && $route->relationLoaded('boardingPoints')) {
            $boardingPoints = $route->boardingPoints;
        }

        $startPoint = $route?->startingPoint?->ghat?->name
            ?: $boardingPoints->firstWhere('type', 'start')?->ghat?->name;
        $endPoint = $route?->endingPoint?->ghat?->name
            ?: $boardingPoints->firstWhere('type', 'end')?->ghat?->name;
        $viaStops = $boardingPoints->where('type', 'via')->values();
        $stoppageNames = $viaStops
            ->map(fn ($point) => $point->ghat?->name)
            ->filter()
            ->values()
            ->all();

        $payload = [
            'id' => (string) $v->id,
            'name' => $v->name,
            'vehicle_type' => $v->vehicle_type,
            'route_id' => (string) $v->route_id,
            'route_name' => $route?->route_name ?? '',
            'start_point' => $startPoint,
            'end_point' => $endPoint,
            'boarding_point' => $startPoint,
            'departure_point' => $startPoint,
            'arrival_point' => $endPoint,
            'stoppages_count' => $viaStops->count(),
            'boarding_points_count' => $boardingPoints->count(),
            'stoppages' => $stoppageNames,
            'passengers_capacity' => (int) $v->passengers_capacity,
            'is_active' => (int) $v->status === 1,
            'registration_no' => $v->registration_no,
            'registration_expiry_date' => $this->formatDateish($v->registration_expiry_date),
            'fitness_expiry_date' => $this->formatDateish($v->fitness_expiry_date),
            'engine_no' => $v->engine_no,
            'vehicle_no' => $v->vehicle_no !== null ? (int) $v->vehicle_no : null,
            'photo' => $v->photo,
            'photo_url' => $this->vehiclePhotoUrl($v->photo),
            'nid_verification_check' => (int) $v->nid_verification_check,
            'number_of_floor' => (int) $v->number_of_floor,
            'ac_available' => (int) $v->ac_available,
            'default_tab' => $v->default_tab,
            'default_floor' => (int) $v->default_floor,
            'allowed_ticket_kinds' => $v->allowed_ticket_kinds ?? [],
        ];

        if ($withImages || $v->relationLoaded('images')) {
            $payload['images'] = $v->images
                ->where('type', 'gallery')
                ->values()
                ->map(fn (VehicleImage $image) => [
                    'id' => (int) $image->id,
                    'image_path' => $image->image_path,
                    'image_url' => $this->vehiclePhotoUrl($image->image_path),
                    'type' => $image->type,
                    'sort_order' => (int) $image->sort_order,
                ])
                ->all();
        }

        return $payload;
    }

    private function vehiclePhotoUrl(?string $photo): ?string
    {
        if ($photo === null || $photo === '') {
            return null;
        }
        if (str_starts_with($photo, 'http://') || str_starts_with($photo, 'https://')) {
            return $photo;
        }
        if (str_contains($photo, '/')) {
            return upload_asset(ltrim($photo, '/'));
        }

        return upload_asset('vehicles/'.$photo);
    }

    /**
     * @return array<string, mixed>
     */
    private function merchantVehicleValidationRules(bool $forUpdate = false, ?int $vehicleId = null): array
    {
        $ticketKeys = array_keys(allowed_ticket_kinds());

        $nameRule = ['required', 'string', 'max:191'];
        if ($forUpdate && $vehicleId) {
            $nameRule = ['sometimes', 'required', 'string', 'max:191', Rule::unique('vehicles', 'name')->ignore($vehicleId)];
        } else {
            $nameRule[] = Rule::unique('vehicles', 'name');
        }

        $regRule = ['nullable', 'string', 'max:191'];
        if ($forUpdate && $vehicleId) {
            $regRule[] = Rule::unique('vehicles', 'registration_no')->ignore($vehicleId);
        } else {
            $regRule[] = Rule::unique('vehicles', 'registration_no');
        }

        return [
            'name' => $nameRule,
            'route_id' => $forUpdate
                ? ['sometimes', 'required', 'integer', 'exists:vehicle_routes,id']
                : ['required', 'integer', 'exists:vehicle_routes,id'],
            'passengers_capacity' => ['nullable', 'integer', 'min:0'],
            'vehicle_type' => ['sometimes', 'nullable', 'string', 'max:191'],
            'registration_no' => $regRule,
            'registration_expiry_date' => ['nullable', 'date'],
            'fitness_expiry_date' => ['nullable', 'date'],
            'engine_no' => ['nullable', 'string', 'max:191'],
            'vehicle_no' => ['nullable', 'integer', 'min:0'],
            'photo' => ['nullable', 'string', 'max:191'],
            'nid_verification_check' => ['sometimes', 'nullable', Rule::in([0, 1, '0', '1', true, false])],
            'number_of_floor' => ['nullable', 'integer', 'min:1', 'max:20'],
            'ac_available' => ['sometimes', 'nullable', Rule::in([0, 1, '0', '1', true, false])],
            'default_tab' => ['sometimes', 'nullable', Rule::in(['cabin', 'seat', 'deck'])],
            'default_floor' => ['nullable', 'integer', 'min:1', 'max:20'],
            'allowed_ticket_kinds' => ['nullable', 'array'],
            'allowed_ticket_kinds.*' => ['string', Rule::in($ticketKeys)],
        ];
    }

    private function resolveVehicleTypeSlug(Request $request, VehicleRoute $route, ?string $existingType, int $ownerId): string
    {
        if (! $request->filled('vehicle_type')) {
            $fallback = $this->normalizeVehicleType((string) ($route->service_type ?: ($existingType ?: 'launch')));
            $this->assertMerchantAllowsServiceType($fallback, $ownerId);

            return $fallback;
        }

        $slug = $this->normalizeVehicleType((string) $request->input('vehicle_type'));

        if ($this->serviceSlugExists($slug)) {
            $this->assertMerchantAllowsServiceType($slug, $ownerId);

            return $slug;
        }

        $routeType = $this->normalizeVehicleType((string) ($route->service_type ?? ''));
        if ($routeType !== '' && $slug === $routeType) {
            $this->assertMerchantAllowsServiceType($routeType, $ownerId);

            return $routeType;
        }

        if ($this->isConfiguredTransportType($slug)) {
            $this->assertMerchantAllowsServiceType($slug, $ownerId);

            return $slug;
        }

        throw ValidationException::withMessages([
            'vehicle_type' => ['Invalid vehicle_type. Use a slug from GET /api/v1/merchant/routes/service-types.'],
        ]);
    }

    private function serviceSlugExists(string $slug): bool
    {
        return Service::query()->where('slug', $slug)->exists();
    }

    private function isConfiguredTransportType(string $slug): bool
    {
        $types = config('transport.vehicle_types', []);
        if (in_array($slug, $types, true)) {
            return true;
        }

        $aliases = config('transport.vehicle_type_alias', []);
        if (isset($aliases[$slug])) {
            return true;
        }

        return in_array($slug, array_values($aliases), true);
    }

    private function assertMerchantAllowsServiceType(string $slug, int $ownerId): void
    {
        $merchant = Merchant::query()->find($ownerId);
        $allowed = $merchant?->allowed_service_types;
        if (! is_array($allowed) || $allowed === []) {
            return;
        }

        $allowed = array_map(fn ($v) => $this->normalizeVehicleType((string) $v), $allowed);
        if (! in_array($slug, $allowed, true)) {
            throw ValidationException::withMessages([
                'vehicle_type' => ['This merchant is not allowed to use service type: '.$slug],
            ]);
        }
    }

    private function toBoolInt(mixed $v): int
    {
        return filter_var($v, FILTER_VALIDATE_BOOLEAN) || $v === 1 || $v === '1' ? 1 : 0;
    }

    /**
     * @param  mixed  $value
     * @return array<int, string>|null
     */
    private function normalizeAllowedTicketKinds(mixed $value): ?array
    {
        if ($value === null) {
            return null;
        }
        if (! is_array($value)) {
            return null;
        }
        $allowed = array_keys(allowed_ticket_kinds());
        $out = [];
        foreach ($value as $item) {
            if (! is_string($item) && ! is_int($item)) {
                continue;
            }
            $k = (string) $item;
            if (in_array($k, $allowed, true)) {
                $out[] = $k;
            }
        }

        return array_values(array_unique($out));
    }

    private function formatDateish(mixed $v): ?string
    {
        if ($v === null || $v === '') {
            return null;
        }
        if ($v instanceof \DateTimeInterface) {
            return $v->format('Y-m-d');
        }

        return (string) $v;
    }

    private function normalizeVehicleType(string $label): string
    {
        $map = [
            'Hotel' => 'hotel',
            'Launch' => 'launch',
            'Bus' => 'bus',
            'Train' => 'train',
            'Boat' => 'boat',
        ];

        return $map[$label] ?? strtolower($label);
    }
}
