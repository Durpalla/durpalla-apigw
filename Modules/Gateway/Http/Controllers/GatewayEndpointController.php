<?php

namespace Modules\Gateway\Http\Controllers;

use Illuminate\Contracts\Support\Renderable;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Gateway\Entities\GatewayCredential;
use Modules\Gateway\Entities\GatewayEndpoint;
use Modules\Gateway\Http\Requests\GatewayCredentialCreateRequest;

class GatewayEndpointController extends Controller
{
    use AuthorizesRequests, ValidatesRequests;

    public function store(GatewayCredentialCreateRequest $request): RedirectResponse
    {
        try {
            $endpoint = GatewayEndpoint::create($request->validated());
            return redirect()->route('gateway.show', [$endpoint->gateway_id, 'tab' => 'endpoint'])->with(['message' => 'Success']);
        } catch (\Exception $exception) {
            return redirect()->back()->with(['message' => $exception->getMessage()]);
        }
    }

    public function destroy(GatewayEndpoint $endpoint): RedirectResponse
    {
        try {
            $endpoint->delete();
            return redirect()->back()->with(['message' => __('Success')]);
        } catch (\Exception $exception) {
            return redirect()->back()->with(['message' => $exception->getMessage()]);
        }
    }
}
