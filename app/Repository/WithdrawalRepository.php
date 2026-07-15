<?php


namespace App\Repository;


use Illuminate\Support\Collection;
use App\Models\AgentWithdrawal;
use App\Repository\Interfaces\WithdrawalRepositoryInterface;

class WithdrawalRepository extends BaseRepository implements WithdrawalRepositoryInterface
{
    public function __construct(AgentWithdrawal $model)
    {
        parent::__construct($model);
    }

    public function all(): Collection
    {
        return parent::all();
    }

    public function get(array $params): array
    {
        $query = $this->model->with(['user', 'officer', 'agentPaymentMethod']);
        if(array_key_exists('status', $params)) {
            $query->where('status', $params['status']);
        }
        if(array_key_exists('user_id', $params) && !empty($params['user_id'])) {
            $query->where('user_id', $params['user_id']);
        }
        if(array_key_exists('date_from', $params) && !empty($params['date_from'])) {
            $query->where('commission_date', '>=', $params['date_from']);
        }
        if(array_key_exists('date_to', $params) && !empty($params['date_to'])) {
            $query->where('commission_date', '<=', $params['date_to']);
        }
        $total = $query->count();
        $withdrawals = $query->take(15)->latest()->get();
        $statuses = config('constants.withdrawal_status');
        return [
            'success' => true,
            'message' => '',
            'total' => $total,
            'data' => $withdrawals->map(function($item, $key) use($statuses){
                return [
                    'id' => $item->id,
                    'user_id' => $item->user_id,
                    'name' => $item->user['name'],
                    'photo' => $item->user['profile_pic'] ? upload_asset($item->user['profile_pic']) : asset('default/avatar.png'),
                    'date' => $item->created_at->format('d/m/Y'),
                    'method' => ($item->agentPaymentMethod) ? $item->agentPaymentMethod['type'] : '---',
                    'balance' => $item->balance,
                    'amount' => $item->amount,
                    'officer' => ($item->officer) ? $item->officer['name'] : '--',
                    'transaction' => $item->transaction_reference,
                    'status' => $statuses[$item->status]
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
}
