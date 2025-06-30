<?php


namespace App\Services;


use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use App\Repository\Interfaces\ServiceRepositoryInterface;

class Services
{
    private $service;
    public function __construct(ServiceRepositoryInterface $service)
    {
        $this->service = $service;
    }

    public function getDropDown(): Collection
    {
        return $this->service->all()->pluck('name', 'id');
    }

    public function getServices(): Collection
    {
        return $this->service->all()->pluck('name', 'slug');
    }

    public function getServiceStatuses(): Collection
    {
        return $this->service->all()->map(function($item, $key) {
            [
                'id' => $item->id,
                'name' => $item->name,
                'status' => $item->status
            ];
        });
    }

    public function getDataTable(array $data): JsonResponse
    {
        $services = $this->service->all();
        $total = $services->count();
        $statuses = config('constants.service_status');

        return response()->json([
            'draw' => request()->get('draw'),
            'recordsTotal' => $total,
            'recordsFiltered' => $total,
            'data' => $services->map(function($item, $key) use($statuses) {
                return [
                    'id' => $item->id,
                    'name' => $item->name,
                    'description' => $item->description,
                    'status' => $item->status
                ];
            })->toArray()
        ]);
    }
}
