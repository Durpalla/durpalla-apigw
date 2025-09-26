<?php

namespace Modules\Gateway\Http\Controllers;

use Illuminate\Contracts\Support\Renderable;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Gateway\Entities\GatewayCredential;
use Modules\Gateway\Entities\Gateway;
use Modules\Gateway\Http\Requests\GatewayCredentialCreateRequest;

class GatewayCredentialController extends Controller
{
    use AuthorizesRequests, ValidatesRequests;

    public function store(GatewayCredentialCreateRequest $request): RedirectResponse
    {
        try {
            $credential = GatewayCredential::create($request->validated());
            return redirect()->route('gateway.show', [$credential->gateway_id, 'tab' => 'credential'])->with(['message' => 'Success']);
        } catch (\Exception $exception) {
            return redirect()->back()->with(['message' => $exception->getMessage()]);
        }
    }

    public function destroy(GatewayCredential $credential): RedirectResponse
    {
        try {
            $credential->delete();
            return redirect()->back()->with(['message' => __('Success')]);
        } catch (\Exception $exception) {
            return redirect()->back()->with(['message' => $exception->getMessage()]);
        }
    }
}
