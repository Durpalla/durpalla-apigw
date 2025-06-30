<?php

namespace App\Http\Controllers\Api\v2;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Requests\WithdrawalCreateRequest;
use App\Repository\Interfaces\WithdrawalRepositoryInterface;
use App\Services\BalanceService;
use App\Services\WithdrawalService;

class ApiWithdrawalController extends Controller
{
    private $withdrawals;
    private $withdrawal;
    private $balanceService;

    public function __construct(
        WithdrawalRepositoryInterface $withdrawal,
        WithdrawalService $withdrawals,
        BalanceService $balanceService
    )
    {
        $this->withdrawal = $withdrawal;
        $this->withdrawals = $withdrawals;
        $this->balanceService = $balanceService;
    }

    /**
     * Display a listing of the resource.
     *
     * @return JsonResponse
     */
    public function index(): JsonResponse
    {
        $data = $this->withdrawal->get(['user_id' => auth()->user()->id]);
        return response()->json($data);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return JsonResponse
     */
    public function create(): JsonResponse
    {
        $agent = auth()->user();
        return response()->json([
            'success' => true,
            'message' => '',
            'balance' => $this->balanceService->getMyBalance(auth()->user()->id),
            'payment_methods' => $this->withdrawals->getMyPaymentMethod($agent)
        ]);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param WithdrawalCreateRequest $request
     * @return JsonResponse
     */
    public function store(WithdrawalCreateRequest $request): JsonResponse
    {
        $data = ['success' => false, 'message' => __('Your withdrawal request not sent')];
        try {
            $this->withdrawal->create($request->validated() + [
                'user_id' => auth()->user()->id
                ]);
            $data['success'] = true;
            $data['message'] = __('Your withdrawal request successfully sent');
        } catch (\Exception $exception) {
            $data['message'] = $exception->getMessage();
        }

        return response()->json($data);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function edit($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id)
    {
        //
    }
}
