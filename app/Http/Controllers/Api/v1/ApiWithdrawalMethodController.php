<?php

namespace App\Http\Controllers\Api\v1;

use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\WithdrawalMethodCreateRequest;
use App\Http\Requests\WithdrawalMethodUpdateRequest;
use App\Repository\Interfaces\AgentPaymentMethodRepositoryInterface;
use App\Services\WithdrawalService;

class ApiWithdrawalMethodController extends Controller
{
    private $withdrawals;
    private $withdrawalMethod;

    public function __construct(
        WithdrawalService $withdrawals,
        AgentPaymentMethodRepositoryInterface $paymentMethodRepository
    )
    {
        $this->withdrawals = $withdrawals;
        $this->withdrawalMethod = $paymentMethodRepository;
    }

    /**
     * Display a listing of the resource.
     *
     * @return JsonResponse
     */
    public function index(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->withdrawalMethod->get(auth()->user()->id)
        ]);
    }


    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return JsonResponse
     */
    public function store(WithdrawalMethodCreateRequest $request): JsonResponse
    {
        $data = ['success' => false, 'message' => __('Cannot add withdrawal method')];
        try {
            $this->withdrawalMethod->create($request->validated() + ['user_id' => auth()->user()->id]);
            $data['success'] = true;
            $data['message'] = __('Your withdrawal method has been successfully saved');
        } catch (\Exception $exception) {
            $data['message'] = $exception->getMessage();
        }

        return response()->json($data);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return JsonResponse
     */
    public function update(WithdrawalMethodUpdateRequest $request, $id): JsonResponse
    {
        $data = ['success' => false, 'message' => __('Cannot update withdrawal method')];
        try {
            $this->withdrawalMethod->update($request->validated(), $id);
        } catch (\Exception $exception) {
            $data['message'] = $exception->getMessage();
        }

        return response()->json($data);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return Response
     */
    public function destroy($id)
    {
        //
    }
}
