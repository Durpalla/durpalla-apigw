<?php


namespace App\Repository;


use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use App\Models\CabinType;
use App\Repository\Interfaces\CabinTypeRepositoryInterface;

class CabinTypeRepository extends BaseRepository implements CabinTypeRepositoryInterface
{
    public function __construct(CabinType $model)
    {
        parent::__construct($model);
    }

    public function all() :Collection
    {
        return Cache::rememberForever('cabin_types', function() {
            return parent::all();
        });
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
