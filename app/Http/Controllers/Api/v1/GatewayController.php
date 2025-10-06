<?php

namespace App\Http\Controllers\Api\v1;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use App\Services\GatewayService;

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
                        return $gateway->only('id', 'name', 'icon');
                    })
            ]
        );
    }
}
