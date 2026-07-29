<?php


namespace App\Repository;


use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use App\Models\AgentCommission;
use App\Models\BookingItem;
use App\Models\Agent;
use App\Repository\Interfaces\AgentRepositoryInterface;
use App\Services\CalculationService;

class AgentRepository extends BaseRepository implements AgentRepositoryInterface
{
    private $calculation;

    public function __construct(Agent $model, CalculationService $calculationService)
    {
        parent::__construct($model);
        $this->calculation = $calculationService;
    }

    public function all(): Collection
    {
        Cache::forget('agents');
        return Cache::rememberForever('agents', function () {
            // Agent.type is an accessor (not a DB column); agents table is already agent-only.
            return $this->model->with(['meta'])->get();
        });
    }

    public function getCommissions($agent_id, array $params): Collection
    {
        $date = (array_key_exists('date', $params)) ? date('Y-m-d', strtotime($params['date'])) : date('Y-m-d');
        return new Collection(AgentCommission::where(['user_id' => $agent_id, 'commission_date' => $date])->orderBy('commission_date', 'DESC')->paginate(15));
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
