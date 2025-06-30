<?php


namespace App\Repository;


use Illuminate\Support\Collection;
use App\Models\AgentPaymentMethod;
use App\Repository\Interfaces\AgentPaymentMethodRepositoryInterface;

class AgentPaymentMethodRepository extends BaseRepository implements AgentPaymentMethodRepositoryInterface
{
    public function __construct(AgentPaymentMethod $model)
    {
        parent::__construct($model);
    }

    public function all(): Collection
    {
        return parent::all();
    }

    public function get(int $userID)
    {
        return $this->model->where('user_id', $userID)->get()->toArray();
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
