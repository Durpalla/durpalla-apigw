<?php

namespace App\Http\Controllers\Api\v1\Agent;

use App\Http\Controllers\Controller;
use App\Models\Agent;
use App\Models\AgentFundTopup;
use App\Services\AgentFundTopupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AgentFundTopupController extends Controller
{
    public function __construct(private readonly AgentFundTopupService $topups)
    {
    }

    public function options(Request $request): JsonResponse
    {
        $agent = $request->user();
        if (! ($agent instanceof Agent)) {
            return response()->json(['success' => false, 'message' => __('Unauthorized')], 401);
        }

        return response()->json($this->topups->options($agent));
    }

    public function gatewayInit(Request $request): JsonResponse
    {
        $agent = $request->user();
        if (! ($agent instanceof Agent)) {
            return response()->json(['success' => false, 'message' => __('Unauthorized')], 401);
        }
        $data = $request->validate([
            'amount' => 'required|numeric|min:1',
            'gateway_id' => 'required|integer',
        ]);

        $result = $this->topups->initiateGateway(
            $agent,
            (float) $data['amount'],
            (int) $data['gateway_id'],
            $request
        );

        return response()->json($result, ! empty($result['success']) ? 200 : 422);
    }

    public function bankTransfer(Request $request): JsonResponse
    {
        $agent = $request->user();
        if (! ($agent instanceof Agent)) {
            return response()->json(['success' => false, 'message' => __('Unauthorized')], 401);
        }
        $data = $request->validate([
            'amount' => 'required|numeric|min:1',
            'bank_reference' => 'nullable|string|max:120',
            'note' => 'nullable|string|max:500',
        ]);

        $result = $this->topups->createBankTransferRequest(
            $agent,
            (float) $data['amount'],
            $data['bank_reference'] ?? null,
            $data['note'] ?? null
        );

        return response()->json($result, ! empty($result['success']) ? 201 : 422);
    }

    public function status(Request $request): JsonResponse
    {
        $agent = $request->user();
        if (! ($agent instanceof Agent)) {
            return response()->json(['success' => false, 'message' => __('Unauthorized')], 401);
        }
        $topupId = (int) $request->input('topup_id');
        if ($topupId <= 0) {
            return response()->json(['success' => false, 'message' => __('topup_id is required')], 422);
        }

        $result = $this->topups->status($agent, $topupId);
        return response()->json($result, ! empty($result['success']) ? 200 : 404);
    }

    public function index(Request $request): JsonResponse
    {
        $agent = $request->user();
        if (! ($agent instanceof Agent)) {
            return response()->json(['success' => false, 'message' => __('Unauthorized')], 401);
        }

        $rows = AgentFundTopup::query()
            ->with('gateway:id,name')
            ->where('user_id', $agent->id)
            ->orderByDesc('id')
            ->limit(50)
            ->get()
            ->map(static function (AgentFundTopup $topup): array {
                $methodLabel = $topup->method === 'bank_transfer'
                    ? 'Bank transfer'
                    : ($topup->gateway?->name ?: 'Gateway');

                return [
                    'id' => $topup->id,
                    'amount' => (float) $topup->amount,
                    'method' => $topup->method,
                    'method_label' => $methodLabel,
                    'status' => $topup->status,
                    'bank_reference' => $topup->bank_reference,
                    'note' => $topup->note,
                    'transaction_ref' => $topup->transaction_ref,
                    'date' => optional($topup->created_at)?->toIso8601String(),
                    'created_at' => optional($topup->created_at)?->toIso8601String(),
                ];
            })
            ->values()
            ->all();

        return response()->json(['success' => true, 'message' => '', 'data' => $rows]);
    }
}
