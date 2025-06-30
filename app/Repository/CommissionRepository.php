<?php


namespace App\Repository;


use Illuminate\Support\Collection;
use App\Models\AgentCommission;
use App\Repository\Interfaces\CommissionRepositoryInterface;

class CommissionRepository extends BaseRepository implements CommissionRepositoryInterface
{
    public function __construct(AgentCommission $model)
    {
        parent::__construct($model);
    }

    public function all(): Collection
    {
        return parent::all();
    }

    public function get(array $params): array
    {
        $query = $this->model->with(['user']);
        if(array_key_exists('user_id', $params)) {
            $query->where('user_id', $params['user_id']);
        }
        if(array_key_exists('date_from', $params)) {
            $query->where('commission_date', '>=', $params['date_from']);
        }
        if(array_key_exists('date_to', $params)) {
            $query->where('commission_date', '<=', $params['date_to']);
        }
        if(array_key_exists('type', $params)) {
            $query->where('type', $params['type']);
        }
        $commissions = $query->orderByDesc('id')->get();
        return [
            'total' => $commissions->count(),
            'data' => $commissions->map(function($item, $key){
                return [
                    'id' => $item->id,
                    'user_id' => $item->user_id,
                    'date' => $item->commission_date,
                    'name' => $item->user['name'],
                    'base_amount' => $item->total_sale,
                    'type' => $item->type,
                    'amount' => $item->amount,
                    'purpose' => $item->purpose
                ];
            })
        ];
    }

    public function create(array $data)
    {
        return parent::create($data);
    }

    public function update(array $data, $id)
    {
        return parent::update($data, $id);
    }

    public function delete($id): int
    {
        return parent::delete($id);
    }
}
