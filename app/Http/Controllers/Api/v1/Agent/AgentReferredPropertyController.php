<?php

namespace App\Http\Controllers\Api\v1\Agent;

use App\Http\Controllers\Controller;
use App\Models\AgentReferredProperty;
use App\Support\AgentApiPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AgentReferredPropertyController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $requestedPage = (int) $request->get('page', 0);
        $page = $requestedPage <= 0 ? 1 : $requestedPage + 1;
        $size = max(1, min(100, (int) $request->get('size', 50)));

        $paginator = AgentReferredProperty::query()
            ->where('agent_id', auth()->id())
            ->orderByDesc('created_at')
            ->paginate($size, ['*'], 'page', $page);

        return response()->json([
            'success' => true,
            'message' => '',
            'data' => [
                'items' => collect($paginator->items())
                    ->map(fn (AgentReferredProperty $property) => AgentApiPresenter::referredProperty($property))
                    ->values()
                    ->all(),
                'page' => max(0, $paginator->currentPage() - 1),
                'size' => $paginator->perPage(),
                'totalElements' => $paginator->total(),
                'totalPages' => $paginator->lastPage(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => __('Refer a merchant instead. Use POST my/referred-merchants.'),
        ], 410);
    }
}
