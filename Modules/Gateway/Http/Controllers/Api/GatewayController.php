<?php

namespace Modules\Gateway\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Modules\Gateway\GatewayService;

class GatewayController extends Controller
{
    private GatewayService $gatewayService;

    public function __construct(GatewayService $gatewayService)
    {
        $this->gatewayService = $gatewayService;
    }

    public function index(): JsonResponse
    {
        return response()->json(
            [
                'success' => true,
                'data' => $this->gatewayService->all()
//                    ->where('type', 'payment')
                    ->map(function ($gateway) {
                        return $gateway->only('id', 'name');
                    })
            ]
        );
    }
}
