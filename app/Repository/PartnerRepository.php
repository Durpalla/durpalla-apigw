<?php


namespace App\Repository;


use App\Constants\AppConst;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use App\Models\AgentCommission;
use App\Models\Partner;
use App\Repository\Interfaces\PartnerRepositoryInterface;
use App\Services\CalculationService;

class PartnerRepository extends BaseRepository implements PartnerRepositoryInterface
{
    private $calculation;

    public function __construct(Partner $model, CalculationService $calculationService)
    {
        parent::__construct($model);
        $this->calculation = $calculationService;
    }

    public function all(): Collection
    {
        Cache::forget('partners');
        return Cache::rememberForever('partners', function () {
            return $this->model->with(['meta', 'roles'])->where('type', AppConst::PARTNER_TYPE)->get();
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
