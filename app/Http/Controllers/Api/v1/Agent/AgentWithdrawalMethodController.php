<?php

namespace App\Http\Controllers\Api\v1\Agent;

use App\Http\Controllers\Controller;
use App\Http\Requests\WithdrawalMethodCreateRequest;
use App\Http\Requests\WithdrawalMethodUpdateRequest;
use App\Models\AgentPaymentMethod;
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

    public function update(WithdrawalMethodUpdateRequest $request, int $id): JsonResponse
    {
        $data = ['success' => false, 'message' => __('Cannot update withdrawal method')];

        try {
            $method = AgentPaymentMethod::query()
                ->where('user_id', auth()->id())
                ->whereKey($id)
                ->first();
            if (! $method) {
                $data['message'] = __('Withdrawal method not found');

                return response()->json($data, 404);
            }

            $method->update($request->validated());
            $data['success'] = true;
            $data['message'] = __('Your withdrawal method has been successfully updated');
            $data['data'] = $method->fresh();
        } catch (\Exception $exception) {
            $data['message'] = $exception->getMessage();
        }

        return response()->json($data);
    }

    public function destroy(int $id): JsonResponse
    {
        $data = ['success' => false, 'message' => __('Cannot remove withdrawal method')];

        try {
            $method = AgentPaymentMethod::query()
                ->where('user_id', auth()->id())
                ->whereKey($id)
                ->first();
            if (! $method) {
                $data['message'] = __('Withdrawal method not found');

                return response()->json($data, 404);
            }

            $method->delete(); // SoftDeletes
            $data['success'] = true;
            $data['message'] = __('Withdrawal method removed');
        } catch (\Exception $exception) {
            $data['message'] = $exception->getMessage();
        }

        return response()->json($data);
    }
}
