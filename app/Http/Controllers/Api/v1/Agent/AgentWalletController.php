<?php

namespace App\Http\Controllers\Api\v1\Agent;

use App\Http\Controllers\Controller;
use App\Models\AccountStatement;
use App\Models\AgentBalance;
use App\Models\AgentCommission;
use App\Services\AccountStatementService;
use App\Services\AgentJourneyCommissionService;
use App\Services\BalanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AgentWalletController extends Controller
{
    public function __construct(
        private readonly BalanceService $balanceService,
        private readonly AccountStatementService $statements,
        private readonly AgentJourneyCommissionService $journeyCommission,
    )
    {
    }

    public function show(): JsonResponse
    {
        $userId = auth()->id();
        $balance = (float) $this->balanceService->getMyBalance($userId);
        $agentBalance = AgentBalance::query()->where('user_id', $userId)->first();

        // "Settled" = already credited via commission:journey-complete (added to
        // the wallet balance). "Pending" = expected commission on confirmed
        // bookings whose journey hasn't completed/settled yet - the customer
        // could still cancel before then, so it isn't in the balance.
        $totalEarned = AgentCommission::netSettledForAgent((int) $userId);
        $pendingCommission = $this->journeyCommission->pendingAmountForAgent((int) $userId);

        return response()->json([
            'success' => true,
            'message' => '',
            'data' => [
                'balance' => $balance,
                'available_balance' => $balance,
                'total_earned' => $totalEarned,
                'pending_commission' => $pendingCommission,
                'total_sale' => (float) AgentCommission::query()
                    ->where('user_id', $userId)
                    ->bookingEarnings()
                    ->sum('total_sale'),
                'last_withdrawal' => $agentBalance ? (float) $agentBalance->last_withdrawal : 0,
            ],
        ]);
    }

    public function statements(Request $request): JsonResponse
    {
        $userId = (int) auth()->id();
        $filters = array_filter([
            'direction' => $request->query('direction'),
            'source' => $request->query('source'),
            'date_from' => $request->query('date_from'),
            'date_to' => $request->query('date_to'),
        ], static fn ($value) => $value !== null && $value !== '');

        $rows = $this->statements->history(
            AccountStatement::ACCOUNT_AGENT,
            $userId,
            (int) $request->query('per_page', 20),
            $filters
        );

        $items = collect($rows->items())->map(static function (AccountStatement $row) {
            return [
                'id' => (int) $row->id,
                'direction' => (string) $row->direction,
                'amount' => (float) $row->amount,
                'balance_before' => (float) $row->balance_before,
                'balance_after' => (float) $row->balance_after,
                'source' => (string) $row->source,
                'reference' => $row->reference,
                'description' => $row->description,
                'created_at' => optional($row->created_at)?->toIso8601String(),
            ];
        })->values();

        return response()->json([
            'success' => true,
            'message' => '',
            'data' => $items,
            'meta' => [
                'current_page' => $rows->currentPage(),
                'last_page' => $rows->lastPage(),
                'per_page' => $rows->perPage(),
                'total' => $rows->total(),
            ],
        ]);
    }
}
