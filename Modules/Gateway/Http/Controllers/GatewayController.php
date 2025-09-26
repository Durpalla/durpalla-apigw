<?php

namespace Modules\Gateway\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Gateway\Entities\Gateway;
use Modules\Gateway\GatewayService;
use Modules\Gateway\Http\Requests\GatewayCreateRequest;
use Modules\Gateway\Http\Requests\GatewayUpdateRequest;
use Modules\Operator\Entities\Operator;

class GatewayController extends Controller
{
    use AuthorizesRequests, ValidatesRequests;
    private GatewayService $gatewayService;

    public function __construct(GatewayService $gatewayService)
    {
        $this->gatewayService = $gatewayService;
    }

    public function index(Request $request)
    {
        if($request->acceptsJson() && $request->wantsJson()) {
            return $this->gatewayService->getDataTables($request);
        }
        return view('gateway::index');
    }

    public function create()
    {
        return view('gateway::create');
    }

    public function store(GatewayCreateRequest $request): RedirectResponse
    {
        return $this->gatewayService->store($request);
    }

    public function show(Gateway $gateway)
    {
        $endpointTypes = $this->gatewayService->getEndpointTypes();
        return view('gateway::show', compact('gateway', 'endpointTypes'));
    }

    public function edit(Gateway $gateway): View
    {
        return view('gateway::edit', compact('gateway'));
    }

    public function update(GatewayUpdateRequest $request, Gateway $gateway): RedirectResponse
    {
        return $this->gatewayService->update($gateway, $request);
    }

    public function suggestion(Request $request): JsonResponse
    {
        return $this->gatewayService->getSuggestions($request);
    }

    public function callAction($method, $parameters)
    {
        if (!in_array($method, ['attachVendor', 'stockSuggestions', 'suggestion'])) {
            $this->authorize($method, Gateway::class);
        }
        return parent::callAction($method, $parameters);
    }
}
