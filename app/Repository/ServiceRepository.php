<?php


namespace App\Repository;


use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use App\Repository\Interfaces\ServiceRepositoryInterface;
use App\Models\Service;

class ServiceRepository extends BaseRepository implements ServiceRepositoryInterface
{
    public function __construct(Service $model)
    {
        parent::__construct($model);
    }

    public function all(): Collection
    {
        return Cache::rememberForever('services', function(){
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
