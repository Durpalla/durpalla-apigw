<?php


namespace App\Services;


use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\JsonResponse;
use App\Models\AgentPaymentMethod;
use App\Repository\Interfaces\WithdrawalRepositoryInterface;

class WithdrawalService
{
    private $repository;

    public function __construct(
        WithdrawalRepositoryInterface $repository
    )
    {
        $this->repository = $repository;
    }

    public function getDataTable(array $data): JsonResponse
    {
        $results = $this->repository->get($data);
        return response()->json([
            'draw' => request()->get('draw'),
            'recordsTotal' => $results['total'],
            'recordsFiltered' => $results['total'],
            'data' => $results['data']->toArray()
        ]);
    }

    public function getMyPaymentMethod(Authenticatable $agent)
    {
        return AgentPaymentMethod::where('user_id', $agent->id)->get()->toArray();
    }
}
