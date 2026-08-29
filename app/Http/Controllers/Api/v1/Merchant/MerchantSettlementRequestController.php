<?php

namespace App\Http\Controllers\Api\v1\Merchant;

use App\Http\Controllers\Controller;
use App\Models\SupervisorSettlementRequest;
use App\Services\FinanceReconciliationService;
use App\Services\FinancialLedgerService;
use App\Support\ResolvesMerchantOwner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class MerchantSettlementRequestController extends Controller
{
    use ResolvesMerchantOwner;

    public function __construct(
        private readonly FinanceReconciliationService $reconciliation,
        private readonly FinancialLedgerService $ledger,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $ownerId = $this->merchantOwnerId($request);

        if (! Schema::hasTable('supervisor_settlement_requests')) {
            return response()->json(['success' => true, 'data' => []]);
        }

        $request->validate([
            'status' => 'nullable|in:pending,approved,declined,all',
            'date' => 'nullable|date_format:Y-m-d',
            'from' => 'nullable|date_format:Y-m-d',
            'to' => 'nullable|date_format:Y-m-d',
        ]);

        $q = SupervisorSettlementRequest::query()
            ->where('merchant_id', $ownerId)
            ->with(['supervisor:id,name,mobile,email']);

        $status = $request->input('status');
        if ($status && $status !== 'all') {
            $q->where('status', $status);
        }

        if ($request->filled('date')) {
            $q->whereDate('date', $request->date);
        } else {
            if ($request->filled('from')) {
                $q->whereDate('date', '>=', $request->from);
            }
            if ($request->filled('to')) {
                $q->whereDate('date', '<=', $request->to);
            }
        }

        $items = $q->orderByDesc('id')->get()->map(function (SupervisorSettlementRequest $r) {
            return [
                'id' => (string) $r->id,
                'date' => $r->date?->format('Y-m-d'),
                'cash_submitted' => (float) $r->cash_submitted,
                'notes' => $r->notes,
                'status' => $r->status,
                'supervisor' => [
                    'id' => (string) $r->supervisor_id,
                    'name' => $r->supervisor?->name ?? '',
                    'mobile' => $r->supervisor?->mobile,
                    'email' => $r->supervisor?->email,
                ],
                'decided_at' => $r->decided_at?->toIso8601String(),
            ];
        })->values();

        return response()->json(['success' => true, 'data' => $items]);
    }

    public function approve(Request $request, int $id): JsonResponse
    {
        $ownerId = $this->merchantOwnerId($request);

        $r = SupervisorSettlementRequest::where('merchant_id', $ownerId)->findOrFail($id);
        if ($r->status !== SupervisorSettlementRequest::STATUS_PENDING) {
            return response()->json(['success' => false, 'message' => 'Only pending requests can be approved.'], 422);
        }

        $date = $r->date?->format('Y-m-d') ?? now()->toDateString();
        $expected = $this->reconciliation->expectedSupervisorCash(
            (int) $r->merchant_id,
            (int) $r->supervisor_id,
            $date,
            $r->trip_id ? (int) $r->trip_id : null
        );
        $variance = round((float) $r->cash_submitted - $expected, 2);

        $r->update([
            'status' => SupervisorSettlementRequest::STATUS_APPROVED,
            'expected_cash' => $expected,
            'variance' => $variance,
            'decided_by' => $request->user()->id,
            'decided_at' => now(),
        ]);

        $this->ledger->recordSupervisorCashApproved($r->fresh(), $expected);

        return response()->json([
            'success' => true,
            'message' => 'Settlement request approved.',
            'data' => [
                'expected_cash' => $expected,
                'cash_submitted' => (float) $r->cash_submitted,
                'variance' => $variance,
            ],
        ]);
    }

    public function decline(Request $request, int $id): JsonResponse
    {
        $ownerId = $this->merchantOwnerId($request);

        $r = SupervisorSettlementRequest::where('merchant_id', $ownerId)->findOrFail($id);
        if ($r->status !== SupervisorSettlementRequest::STATUS_PENDING) {
            return response()->json(['success' => false, 'message' => 'Only pending requests can be declined.'], 422);
        }

        $r->update([
            'status' => SupervisorSettlementRequest::STATUS_DECLINED,
            'decided_by' => $request->user()->id,
            'decided_at' => now(),
        ]);

        return response()->json(['success' => true, 'message' => 'Settlement request declined.']);
    }
}

