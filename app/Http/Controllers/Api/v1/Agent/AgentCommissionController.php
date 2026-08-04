<?php

namespace App\Http\Controllers\Api\v1\Agent;

use App\Http\Controllers\Controller;
use App\Models\AgentCommission;
use App\Models\BookingItem;
use App\Services\AgentJourneyCommissionService;
use App\Services\CalculationService;
use App\Support\AgentApiPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AgentCommissionController extends Controller
{
    public function __construct(
        private readonly AgentJourneyCommissionService $journeyCommission,
        private readonly CalculationService $calculation,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $page = max(1, (int) $request->get('page', 1));
        $size = max(1, min(50, (int) $request->get('size', 15)));
        $agentId = (int) auth()->id();

        // Page 1 prepends unsettled (pending) commissions so agents see earnings
        // before journey settlement credits the wallet.
        $pendingRows = [];
        if ($page === 1) {
            $pendingRows = $this->journeyCommission->pendingItemsForAgent($agentId)
                ->map(function (BookingItem $item) {
                    $amount = (float) $this->calculation->calculateAgentCommission($item->toArray());
                    if ($amount <= 0) {
                        return null;
                    }

                    return AgentApiPresenter::pendingCommission($item, $amount);
                })
                ->filter()
                ->values()
                ->all();
        }

        $settledSize = max(1, $size - count($pendingRows));
        $paginator = AgentCommission::query()
            ->with(['bookingItem.booking', 'bookingItem.vehicle'])
            ->where('user_id', $agentId)
            ->bookingEarnings()
            ->orderByDesc('commission_date')
            ->orderByDesc('id')
            ->paginate($settledSize, ['*'], 'page', $page);

        $settledRows = collect($paginator->items())
            ->map(fn (AgentCommission $commission) => AgentApiPresenter::commission($commission))
            ->values()
            ->all();

        $data = array_values(array_merge($pendingRows, $settledRows));

        return response()->json([
            'success' => true,
            'message' => '',
            'total' => $paginator->total() + count($pendingRows),
            'data' => $data,
        ]);
    }
}
