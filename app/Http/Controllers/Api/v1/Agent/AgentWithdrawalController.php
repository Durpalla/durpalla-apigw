<?php

namespace App\Http\Controllers\Api\v1\Agent;

use App\Http\Controllers\Controller;
use App\Http\Requests\WithdrawalCreateRequest;
use App\Models\AgentBalance;
use App\Repository\Interfaces\WithdrawalRepositoryInterface;
use App\Services\BalanceService;
use App\Services\WithdrawalService;
use Illuminate\Http\JsonResponse;

class AgentWithdrawalController extends Controller
{
    public function __construct(
        private readonly WithdrawalRepositoryInterface $withdrawalRepository,
        private readonly WithdrawalService $withdrawalService,
        private readonly BalanceService $balanceService,
    ) {
    }

    public function index(): JsonResponse
    {
        return response()->json(
            $this->withdrawalRepository->get(['user_id' => auth()->id()])
        );
    }

    public function init(): JsonResponse
    {
        $user = auth()->user();
        $balance = (float) $this->balanceService->getMyBalance($user->id);
        $agentBalance = AgentBalance::query()->where('user_id', $user->id)->first();

        return response()->json([
            'success' => true,
            'message' => '',
            'balance' => [
                'balance' => $balance,
                'last_withdrawal' => $agentBalance ? (float) $agentBalance->last_withdrawal : 0,
            ],
            'payment_methods' => $this->withdrawalService->getMyPaymentMethod($user),
        ]);
    }

    public function store(WithdrawalCreateRequest $request): JsonResponse
    {
        $data = ['success' => false, 'message' => __('Your withdrawal request not sent')];

        try {
            $this->withdrawalRepository->create($request->validated() + [
                'user_id' => auth()->id(),
            ]);
            $data['success'] = true;
            $data['message'] = __('Your withdrawal request successfully sent');
        } catch (\Exception $exception) {
            $data['message'] = $exception->getMessage();
        }

        return response()->json($data);
    }
}
