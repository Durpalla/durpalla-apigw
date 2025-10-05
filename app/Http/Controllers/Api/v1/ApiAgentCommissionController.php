<?php

namespace App\Http\Controllers\Api\v1;

use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use App\Services\AgentService;

class ApiAgentCommissionController extends Controller
{
    private $agent;
    public function __construct(AgentService $agentService)
    {
        $this->agent = $agentService;
    }

    /**
     * Display a listing of the resource.
     *
     * @return JsonResponse
     */
    public function index(): JsonResponse
    {
        return $this->agent->dailyCommissions(auth()->user()->id, request()->all());
    }
}
