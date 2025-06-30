<?php


namespace App\Repository;


use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use App\Models\Party;
use App\Repository\Interfaces\PartyRepositoryInterface;

class PartyRepository extends BaseRepository implements PartyRepositoryInterface
{
    public function __construct(Party $model)
    {
        parent::__construct($model);
    }

    public function all() : Collection
    {
        return Cache::rememberForever('parties', function() {
            return parent::with(['user'])->get();
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
