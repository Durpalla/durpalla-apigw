<?php

namespace App\Http\Controllers\Api\v1\Agent;

use App\Http\Controllers\Controller;
use App\Http\Requests\WithdrawalMethodCreateRequest;
use App\Repository\Interfaces\AgentPaymentMethodRepositoryInterface;
use Illuminate\Http\JsonResponse;

class AgentWithdrawalMethodController extends Controller
{
    public function __construct(
        private readonly AgentPaymentMethodRepositoryInterface $paymentMethodRepository,
    ) {
    }

    public function index(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->paymentMethodRepository->get(auth()->id()),
        ]);
    }

    public function store(WithdrawalMethodCreateRequest $request): JsonResponse
    {
        $data = ['success' => false, 'message' => __('Cannot add withdrawal method')];

        try {
            $method = $this->paymentMethodRepository->create(
                $request->validated() + ['user_id' => auth()->id()]
            );
            $data['success'] = true;
            $data['message'] = __('Your withdrawal method has been successfully saved');
            $data['data'] = $method;
        } catch (\Exception $exception) {
            $data['message'] = $exception->getMessage();
        }

        return response()->json($data);
    }
}
