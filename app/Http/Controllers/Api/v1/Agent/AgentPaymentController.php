<?php

namespace App\Http\Controllers\Api\v1\Agent;

use App\Http\Controllers\Controller;
use App\Models\Agent;
use App\Services\AgentPaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AgentPaymentController extends Controller
{
    public function __construct(private readonly AgentPaymentService $payments)
    {
    }

    /**
     * POST /api/v1/agent/payment/make
     * Body: order_id, gateway_id
     */
    public function make(Request $request): JsonResponse
    {
        $agent = auth()->user();
        if (! ($agent instanceof Agent)) {
            return response()->json(['success' => false, 'message' => __('Unauthorized')], 401);
        }

        $orderId = (int) $request->input('order_id');
        $gatewayId = (int) $request->input('gateway_id');
        if ($orderId <= 0 || $gatewayId <= 0) {
            return response()->json([
                'success' => false,
                'message' => __('order_id and gateway_id are required'),
            ], 422);
        }

        $result = $this->payments->initiate($agent, $orderId, $gatewayId, $request);

        return response()->json($result, ! empty($result['success']) ? 200 : 422);
    }

    /**
     * GET /api/v1/agent/payment/status?order_id=
     */
    public function status(Request $request): JsonResponse
    {
        $agent = auth()->user();
        if (! ($agent instanceof Agent)) {
            return response()->json(['success' => false, 'message' => __('Unauthorized')], 401);
        }

        $orderId = (int) $request->input('order_id');
        if ($orderId <= 0) {
            return response()->json([
                'success' => false,
                'message' => __('order_id is required'),
            ], 422);
        }

        $result = $this->payments->status($agent, $orderId);

        return response()->json($result, ! empty($result['success']) ? 200 : 404);
    }
}
