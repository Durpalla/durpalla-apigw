<?php


namespace Modules\BroadCast\Repository;


use Illuminate\Support\Collection;
use App\Repository\BaseRepository;
use Modules\BroadCast\Entities\BroadCast;

class BroadcastRepository extends BaseRepository implements BroadcastRepositoryInterface
{
    public function __construct(BroadCast $model)
    {
        parent::__construct($model);
    }

    public function all(): Collection
    {
        return parent::all();
    }

    public function create(array $data)
    {
        return $this->model->create($data);
    }

    public function update(array $data, $id)
    {
        return parent::update($data, $id);
    }

    public function delete($id): int
    {
        return parent::delete($id);
    }

    public function find($booking_id)
    {
        return parent::find($booking_id);
    }
}
