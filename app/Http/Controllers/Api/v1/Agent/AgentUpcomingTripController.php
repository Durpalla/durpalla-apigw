<?php

namespace App\Http\Controllers\Api\v1\Agent;

use App\Http\Controllers\Controller;
use App\Models\Agent;
use App\Services\AgentUpcomingTripService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AgentUpcomingTripController extends Controller
{
    public function __construct(private readonly AgentUpcomingTripService $trips)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $agent = $this->agent();
        if (! $agent) {
            return $this->unauthorized();
        }

        $meta = $this->trips->favouriteMeta($agent);
        $filters = $request->only(['departure', 'destination', 'vehicle_type', 'favourites_only', 'page', 'size']);

        if (! $request->has('favourites_only')) {
            $filters['favourites_only'] = $meta['has_favourites'];
        }

        $page = $this->trips->upcoming($agent, $filters);
        $favouritesOnly = array_key_exists('favourites_only', $filters)
            ? filter_var($filters['favourites_only'], FILTER_VALIDATE_BOOLEAN)
            : $meta['has_favourites'];

        return response()->json([
            'success' => true,
            'message' => '',
            'data' => $page->items(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
                'has_favourites' => $meta['has_favourites'],
                'favourites_only' => $favouritesOnly,
                'favourite_count' => count($meta['favourite_vehicle_ids']),
            ],
        ]);
    }

    public function favourites(): JsonResponse
    {
        $agent = $this->agent();
        if (! $agent) {
            return $this->unauthorized();
        }

        $items = $this->trips->listFavourites($agent);

        return response()->json([
            'success' => true,
            'message' => '',
            'data' => $items,
            'meta' => [
                'has_favourites' => $items->isNotEmpty(),
                'count' => $items->count(),
            ],
        ]);
    }

    public function addFavourite(Request $request): JsonResponse
    {
        $agent = $this->agent();
        if (! $agent) {
            return $this->unauthorized();
        }

        $request->validate([
            'vehicle_id' => 'required|integer|min:1',
        ]);

        try {
            $data = $this->trips->addFavourite($agent, (int) $request->input('vehicle_id'));
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => __('Added to favourites'),
            'data' => $data,
        ]);
    }

    public function removeFavourite(int $vehicleId): JsonResponse
    {
        $agent = $this->agent();
        if (! $agent) {
            return $this->unauthorized();
        }

        $this->trips->removeFavourite($agent, $vehicleId);

        return response()->json([
            'success' => true,
            'message' => __('Removed from favourites'),
            'data' => [
                'vehicle_id' => $vehicleId,
                'is_favourite' => false,
            ],
        ]);
    }

    private function agent(): ?Agent
    {
        $user = auth()->user();

        return $user instanceof Agent ? $user : null;
    }

    private function unauthorized(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => __('Unauthorized'),
        ], 401);
    }
}
