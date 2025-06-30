<?php


namespace App\Repository;


use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use App\Models\Gateway;
use App\Repository\Interfaces\GatewayRepositoryInterface;

class GatewayRepository extends BaseRepository implements GatewayRepositoryInterface
{
    public function __construct(Gateway $model)
    {
        parent::__construct($model);
    }

    public function all(): Collection
    {
        return Cache::rememberForever('gateways', function() {
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

    public function delete($id)
    {
        return parent::delete($id);
    }
}
