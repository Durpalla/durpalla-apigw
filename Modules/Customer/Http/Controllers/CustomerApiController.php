<?php

namespace Modules\Customer\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Modules\Customer\CustomerService;
use Modules\Customer\Http\Requests\WantToBePartnerRequest;

class CustomerApiController extends Controller
{
    public $customer;
    public function __construct(CustomerService $customerService)
    {
        $this->customer = $customerService;
    }

    /**
     * Display a listing of the resource.
     * @return JsonResponse
     */
    public function wantTobePartner(WantToBePartnerRequest $request): JsonResponse
    {
        $data = ['success' => false, 'message' => __('Cannot process request')];
        try {
            $this->customer->sendPartnerRequest($request->all());
            $data['success'] = true;
            $data['message'] = __('Your partner request successfully sent to ' . config('app.name'));
        } catch (\Throwable $exception) {
            $data['message'] = $exception->getMessage();
        }

        return response()->json($data);
    }
}
