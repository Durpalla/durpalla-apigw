<?php

namespace App\Http\Controllers\Api\v1\Merchant;

use App\Http\Controllers\Controller;
use App\Models\Ghat;
use App\Models\Merchant;
use App\Models\Service;
use App\Models\VehicleRoute;
use App\Support\ResolvesMerchantOwner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Merchant Desk — vehicle routes & ghat suggestions (aligned with dashboard route create).
 */
class MerchantRouteController extends Controller
{
    use ResolvesMerchantOwner;

    public function serviceTypes(Request $request): JsonResponse
    {
        $ownerId = $this->merchantOwnerId($request);

        $merchant = Merchant::query()->find($ownerId);
        $allowed = $merchant ? $merchant->allowed_service_types : null;
        if (! is_array($allowed)) {
            $allowed = [];
        }
        $allowed = array_values(array_unique(array_filter(array_map('strval', $allowed))));

        $q = Service::query()->orderBy('name');
        if (count($allowed) > 0) {
            $transportAllowed = array_values(array_filter(
                $allowed,
                fn ($type) => strtolower((string) $type) !== 'hotel'
            ));
            if (count($transportAllowed) > 0) {
                $q->whereIn('slug', $transportAllowed);
            } else {
                $q->whereRaw('1 = 0');
            }
        }

        $rows = $q->get(['slug', 'name']);

        return response()->json([
            'success' => true,
            'data' => $rows->map(fn (Service $s) => [
                'slug' => $s->slug,
                'name' => $s->name,
            ]),
        ]);
    }

    /**
     * Ghat dropdown data (same filters as dashboard.ghat.suggest).
     */
    public function ghatSuggest(Request $request): JsonResponse
    {
        $this->merchantOwnerId($request);
        $term = (string) $request->get('term', '');
        $serviceType = $request->get('service_type');
        $perPage = min(max((int) $request->get('per_page', 30), 1), 100);

        $query = Ghat::query()->select(['id', 'name', 'service_type']);
        if ($term !== '') {
            $query->where('name', 'LIKE', '%'.$term.'%');
        }
        if ($serviceType !== null && $serviceType !== '') {
            $query->where('service_type', $serviceType);
        }
        $items = $query->orderBy('name')->limit($perPage)->get();

        return response()->json([
            'success' => true,
            'data' => $items->map(fn (Ghat $g) => [
                'id' => $g->id,
                'name' => $g->name,
                'service_type' => $g->service_type,
            ]),
        ]);
    }

    /**
     * Preview route name from start/end ghat ids (same as dashboard.route.name).
     */
    public function namePreview(Request $request): JsonResponse
    {
        $this->merchantOwnerId($request);
        $request->validate([
            'starting' => 'required|integer|exists:ghats,id',
            'ending' => 'required|integer|exists:ghats,id',
        ]);
        $starting = (int) $request->get('starting');
        $ending = (int) $request->get('ending');
        $startingPoint = Ghat::findOrFail($starting);
        $endingPoint = Ghat::findOrFail($ending);
        $routeName = $startingPoint->name.' - '.$endingPoint->name;

        return response()->json([
            'success' => true,
            'route_name' => $routeName,
        ]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $this->merchantOwnerId($request);

        $route = VehicleRoute::query()
            ->with(['startingPoint.ghat', 'endingPoint.ghat'])
            ->findOrFail($id);

        $stops = DB::table('route_properties')
            ->leftJoin('ghats', 'ghats.id', '=', 'route_properties.ghat_id')
            ->where('route_properties.route_id', $route->id)
            ->orderBy('route_properties.serial_num')
            ->get([
                'route_properties.id',
                'route_properties.ghat_id',
                'route_properties.type',
                'route_properties.serial_num',
                'ghats.name as ghat_name',
            ])
            ->map(fn ($stop) => [
                'id' => (string) $stop->id,
                'ghat_id' => (string) $stop->ghat_id,
                'name' => (string) ($stop->ghat_name ?: 'Stop'),
                'type' => (string) $stop->type,
                'sequence' => (int) $stop->serial_num,
            ])
            ->values()
            ->all();

        return response()->json([
            'success' => true,
            'data' => [
                'id' => (string) $route->id,
                'route_name' => $route->route_name,
                'route_no' => $route->route_no,
                'route_type' => $route->route_type,
                'service_type' => $route->service_type,
                'start_ghat' => $route->startingPoint?->ghat?->name,
                'end_ghat' => $route->endingPoint?->ghat?->name,
                'boarding_points' => $stops,
            ],
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $this->merchantOwnerId($request);
        $query = VehicleRoute::query()
            ->with([
                'startingPoint.ghat',
                'endingPoint.ghat',
            ]);

        if ($request->filled('service_type')) {
            $query->where('service_type', $request->service_type);
        }

        if ($request->filled('search')) {
            $s = '%'.$request->search.'%';
            $query->where('route_name', 'LIKE', $s);
        }

        $limit = min(max((int) $request->get('per_page', 100), 1), 200);
        $routes = $query->orderByDesc('id')->limit($limit)->get();

        $data = $routes->map(function (VehicleRoute $r) {
            return [
                'id' => (string) $r->id,
                'route_name' => $r->route_name,
                'route_no' => $r->route_no,
                'route_type' => $r->route_type,
                'service_type' => $r->service_type,
                'start_ghat' => $r->startingPoint?->ghat?->name,
                'end_ghat' => $r->endingPoint?->ghat?->name,
            ];
        });

        return response()->json(['success' => true, 'data' => $data]);
    }

    /**
     * Create route + route_properties (boarding points).
     *
     * @bodyParam boarding_points array { ghat_id, type: start|via|end, position }
     */
    public function store(Request $request): JsonResponse
    {
        $this->merchantOwnerId($request);

        $validated = $request->validate([
            'route_name' => 'required|string|max:191',
            'route_no' => ['nullable', 'integer', Rule::unique('vehicle_routes', 'route_no')],
            'route_type' => 'nullable|string|max:191|in:direct,local',
            'service_type' => 'required|string|exists:services,slug',
            'boarding_points' => 'required|array|min:2',
            'boarding_points.*.ghat_id' => 'required|integer|exists:ghats,id',
            'boarding_points.*.type' => 'required|string|in:start,via,end',
            'boarding_points.*.position' => 'required|integer|min:1|max:99',
        ]);

        $points = collect($validated['boarding_points'])->sortBy('position')->values();

        $starts = $points->where('type', 'start');
        $ends = $points->where('type', 'end');
        if ($starts->count() !== 1 || $ends->count() !== 1) {
            return response()->json([
                'success' => false,
                'message' => 'Exactly one starting point and one ending point are required.',
            ], 422);
        }

        $ghatIds = $points->pluck('ghat_id')->all();
        if (count($ghatIds) !== count(array_unique($ghatIds))) {
            return response()->json([
                'success' => false,
                'message' => 'Duplicate ghats are not allowed on the same route.',
            ], 422);
        }

        $routeNo = isset($validated['route_no']) ? (int) $validated['route_no'] : null;
        if ($routeNo === null) {
            $routeNo = (int) (VehicleRoute::max('route_no') ?? 0) + 1;
            while (VehicleRoute::where('route_no', $routeNo)->exists()) {
                $routeNo++;
            }
        }

        $routeType = $validated['route_type'] ?? 'direct';

        try {
            DB::beginTransaction();
            $route = new VehicleRoute;
            $route->route_name = $validated['route_name'];
            $route->route_no = $routeNo;
            $route->route_type = $routeType;
            $route->created_by = auth()->id();
            $route->service_type = $validated['service_type'];
            $route->save();

            foreach ($points as $row) {
                DB::table('route_properties')->insert([
                    'route_id' => $route->id,
                    'name' => (string) $row['ghat_id'],
                    'ghat_id' => (int) $row['ghat_id'],
                    'type' => $row['type'],
                    'user_id' => auth()->id(),
                    'serial_num' => (int) $row['position'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            DB::commit();
            Cache::forget('route_dropdowns');
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }

        $route->load(['startingPoint.ghat', 'endingPoint.ghat']);

        return response()->json([
            'success' => true,
            'message' => 'Route created.',
            'data' => [
                'id' => (string) $route->id,
                'route_name' => $route->route_name,
                'route_no' => $route->route_no,
                'route_type' => $route->route_type,
                'service_type' => $route->service_type,
                'start_ghat' => $route->startingPoint?->ghat?->name,
                'end_ghat' => $route->endingPoint?->ghat?->name,
            ],
        ], 201);
    }
}
