<?php

namespace Modules\Gateway;

use App\Helpers\CommonHelper;
use App\Helpers\LogHelper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Modules\Gateway\Entities\Gateway;
use Modules\Gateway\Http\Requests\GatewayCreateRequest;
use Modules\Gateway\Http\Requests\GatewayUpdateRequest;
use Modules\Provider\Entities\Provider;

class GatewayService
{
    public function all()
    {
        return Cache::remember('gateways', 3600, function () {
            return Gateway::where('status', Gateway::ACTIVE)->get();
        });
    }

    public function getDataTables(Request $request): JsonResponse
    {
        return datatables()->eloquent(
            Gateway::query()
        )
            ->addColumn('status', function (Gateway $gateway) {
                return $gateway->nice_status;
            })
            ->addColumn('actions', function (Gateway $gateway) {
                $btns =  '<a href="' . route('gateway.show', $gateway->id) . '" class="btn btn-sm btn-default"><i class="fa fa-eye"></i></a>';
                if($gateway->is_editable) {
                    $btns .= '<a href="' . route('gateway.edit', $gateway->id) . '" class="btn btn-sm btn-primary"><i class="fa fa-edit"></i></a>';
                }
                return $btns;
            })
            ->rawColumns(['actions'])
            ->toJson();
    }

    public function store(GatewayCreateRequest $request): RedirectResponse
    {
        try {
            Gateway::create($request->validated());
            return redirect()->route('gateway.index')->with(['status' => true, 'message' => __('Gateway successfully created')]);;
        } catch (\Exception $exception) {
            LogHelper::exception($exception);
            return redirect()->back()->withInput($request->all())->with(['status' => false, 'message' => __('Failed to create gateway')]);
        }
    }

    public function update(Gateway $gateway, GatewayUpdateRequest $request): RedirectResponse
    {
        try {
            $gateway->update($request->validated());
            return redirect()->route('gateway.index')->with(['status' => true, 'message' => __('Attribute successfully deleted')]);;
        } catch (\Exception $exception) {
            LogHelper::exception($exception);
            return redirect()->back()->withInput($request->all())->with(['status' => false, 'message' => __('Failed to delete gateway')]);;
        }
    }

    public function getSuggestions(Request $request): JsonResponse
    {
        try {
            $data = Gateway::all()
                ->filter(function ($gateway) use ($request) {
                    $matched = true;
                    if($request->has('provider_id')) {
                        $provider = Provider::find($request->input('provider_id'));
                        $matched = (in_array($gateway->id, $provider->gateway_ids));
                    }
                    if($request->has('term')) {
                        $matched = CommonHelper::matchText($gateway->name, $request->input('term'));
                    }
                    return $matched;
                })
                ->map(function ($provider, $key) {
                    return [
                        'id' => $provider->id,
                        'text' => $provider->name
                    ];
                })->values();
            return response()->json(['results' => $data]);
        } catch (\Exception $exception) {
            return response()->json(['message' => __('No data!')]);
        }
    }

    public function getEndpointTypes(): array
    {
        return [
            'token' => 'Token endpoint',
            'info' => 'Info endpoint',
            'validate' => 'Validate endpoint',
            'create' => 'Create endpoint',
            'execute' => 'Execute endpoint',
            'verify' => 'Verify endpoint',
            'callback' => 'IPN Callback endpoint'
        ];
    }
}
