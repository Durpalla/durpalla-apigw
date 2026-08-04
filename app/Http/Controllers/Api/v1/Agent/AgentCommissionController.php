<?php

namespace App\Http\Controllers\Api\v1\Agent;

use App\Http\Controllers\Controller;
use App\Models\AgentCommission;
use App\Support\AgentApiPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AgentCommissionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $page = max(1, (int) $request->get('page', 1));
        $size = max(1, min(50, (int) $request->get('size', 15)));

        // Only settled (credited) commission rows belong here - fund debits,
        // cancellations, and withdrawals show up on the account statement instead.
        $paginator = AgentCommission::query()
            ->where('user_id', auth()->id())
            ->where('type', 'credit')
            ->where('purpose', 'commission')
            ->orderByDesc('commission_date')
            ->orderByDesc('id')
            ->paginate($size, ['*'], 'page', $page);

        return response()->json([
            'success' => true,
            'message' => '',
            'total' => $paginator->total(),
            'data' => collect($paginator->items())
                ->map(fn (AgentCommission $commission) => AgentApiPresenter::commission($commission))
                ->values()
                ->all(),
        ]);
    }
}
