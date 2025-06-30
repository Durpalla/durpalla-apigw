<?php


namespace App\Repository;


use Illuminate\Support\Collection;
use App\Models\VehicleRoute;
use App\Repository\Interfaces\RouteRepositoryInterface;

class RouteRepository extends BaseRepository implements RouteRepositoryInterface
{
    public function __construct(VehicleRoute $model)
    {
        parent::__construct($model);
    }

    public function all(): Collection
    {
        return parent::all();
    }
}
