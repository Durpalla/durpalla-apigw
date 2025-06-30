<?php

namespace App\Http\Controllers\Dashboard;

use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use App\Services\AgentService;
use App\Services\CommissionService;

class AgentCommissionController extends Controller
{
    private $agent;
    private $commissions;

    public function __construct(
        AgentService $agentService,
        CommissionService $commissions
    )
    {
        $this->agent = $agentService;
        $this->commissions = $commissions;
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        if(request()->wantsJson()) {
            return $this->commissions->getDataTable(request()->all());
        }

        return view('agent.commission.index')->withTitle('Commissions');
    }

    /**
     * Display a listing of the resource.
     *
     * @param $id
     * @return JsonResponse
     */
    public function show($id): JsonResponse
    {
        if(request()->wantsJson()) {
            return response()->json($this->agent->getCommissions($id, request()->all()));
        }
    }
}
