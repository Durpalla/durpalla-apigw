<?php

namespace App\Http\Controllers\Api\v1\Agent;

use App\Http\Controllers\Controller;
use App\Models\AgentCommission;
use App\Models\AgentCommissionAccrual;
use App\Support\AgentApiPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AgentCommissionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $page = max(1, (int) $request->get('page', 1));
        $size = max(1, min(50, (int) $request->get('size', 15)));
        $agentId = (int) auth()->id();
        $offset = ($page - 1) * $size;

        $pendingQuery = AgentCommissionAccrual::query()
            ->where('agent_id', $agentId)
            ->pending();
        $pendingTotal = (clone $pendingQuery)->count();
        $pendingRows = $offset < $pendingTotal
            ? (clone $pendingQuery)
                ->with(['booking', 'bookingItem.vehicle'])
                ->orderByDesc('id')
                ->skip($offset)
                ->take($size)
                ->get()
                ->map(fn (AgentCommissionAccrual $accrual) => AgentApiPresenter::pendingAccrual($accrual))
                ->values()
                ->all()
            : [];

        $settledQuery = AgentCommission::query()
            ->with(['accrual.booking', 'bookingItem.booking', 'bookingItem.vehicle'])
            ->where('user_id', $agentId)
            ->commissionLedger();
        $settledTotal = (clone $settledQuery)->count();
        $settledRows = $settledQuery
            ->orderByDesc('commission_date')
            ->orderByDesc('id')
            ->skip(max(0, $offset - $pendingTotal))
            ->take($size - count($pendingRows))
            ->get()
            ->map(fn (AgentCommission $commission) => AgentApiPresenter::commission($commission))
            ->values()
            ->all();

        return response()->json([
            'success' => true,
            'message' => '',
            'total' => $pendingTotal + $settledTotal,
            'data' => array_values(array_merge($pendingRows, $settledRows)),
        ]);
    }
}
