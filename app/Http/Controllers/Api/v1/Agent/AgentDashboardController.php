<?php

namespace App\Http\Controllers\Api\v1\Agent;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Services\AgentDashboardService;
use App\Support\AgentApiPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AgentDashboardController extends Controller
{
    public function __construct(private readonly AgentDashboardService $dashboardService)
    {
    }

    public function show(Request $request): JsonResponse
    {
        $chartDays = (int) $request->get('chart_days', 7);
        $data = $this->dashboardService->dashboard((int) auth()->id(), $chartDays);

        return response()->json([
            'success' => true,
            'message' => '',
            'data' => $data,
        ]);
    }
}
