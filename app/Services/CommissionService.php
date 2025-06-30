<?php


namespace App\Services;


use Illuminate\Http\JsonResponse;
use App\Repository\Interfaces\CommissionRepositoryInterface;

class CommissionService
{
    /**
     * @var CommissionRepositoryInterface
     */
    private $repository;

    public function __construct(CommissionRepositoryInterface $repository) {
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
}
