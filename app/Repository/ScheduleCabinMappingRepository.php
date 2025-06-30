<?php


namespace App\Repository;


use Illuminate\Support\Collection;
use App\Repository\Interfaces\ScheduleCabinMappingRepositoryInterface;
use App\Models\ScheduleCabinMapping;

class ScheduleCabinMappingRepository extends BaseRepository implements ScheduleCabinMappingRepositoryInterface
{
    public function __construct(ScheduleCabinMapping $model)
    {
        parent::__construct($model);
    }

    public function all(): Collection
    {
        return parent::all();
    }

    public function create(array $data)
    {
        return parent::create($data);
    }

    public function update(array $data, $id)
    {
        return parent::update($data, $id);
    }
}
