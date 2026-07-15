<?php


namespace App\Services;


use App\Constants\AppConst;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\Models\BookingItem;
use App\Models\Agent;
use App\Models\AgentIncentive;
use App\Repository\Interfaces\AgentRepositoryInterface;
use App\Models\UserMeta;

class AgentService
{
    /**
     * @var AgentRepositoryInterface
     */
    private $agent;
    private $calculation;

    public function __construct(
        AgentRepositoryInterface $agentRepository,
        CalculationService $calculationService
    )
    {
        $this->agent = $agentRepository;
        $this->calculation = $calculationService;
    }

    public function getIndex(array $data): JsonResponse
    {
        $agents = $this->agent->all();
        if(array_key_exists('status', $data)) {
            $agents = $agents->where('status', $data['status']);
        }
        $total = $agents->count();
        $incentive_types = config('constants.incentive_types');
        $statuses = config('constants.user_status');
        return response()->json([
            'draw' => request()->get('draw'),
            'recordsTotal' => $total,
            'recordsFiltered' => $total,
            'data' => $agents->map(function($item, $key) use($incentive_types, $statuses) {
                return [
                    'id' => $item->id,
                    'name' => $item->name,
                    'email' => $item->email,
                    'mobile' => $item->mobile,
                    'address' => ($item->meta) ? $item->meta['address'] : null,
                    'photo' =>($item->profile_pic) ? upload_asset($item->profile_pic) : asset('default/avatar.png'),
                    'incentive' => ($item->incentive) ? $item->incentive->incentive . '(' . $incentive_types[$item->incentive->incentive_type] . ')' : 0,
                    'created_at' => $item->created_at->format('d/m/Y h:ia'),
                    'status' => $statuses[$item->status]
                ];
            })->toArray()
        ]);
    }

    public function create(array $data)
    {
        try {
            DB::transaction(function() use($data) {
                $agent = Agent::create(['password' => Hash::make($data['password']), 'type' => AppConst::AGENT_TYPE, 'email_verified_at' => now(), 'status' => AppConst::USER_ACTIVE] + $data);
                $meta = UserMeta::create($data + ['user_id' => $agent->id, 'created_by' => auth()->user()->id, 'platform' => 'counter']);
                $incentives = AgentIncentive::create($data + ['agent_id' => $agent->id]);
            }, 2);
            return true;
        } catch (\Exception $exception) {
            return false;
        }
    }

    public function update(array $data, int $id)
    {
        try {
            DB::transaction(function() use($data, $id) {
                if (!empty($data['password'])) {
                    $data['password'] = Hash::make($data['password']);
                }
                $this->agent->update($data, $id);
            }, 2);
            return true;
        } catch (\Exception $exception) {
            return false;
        }
    }

    public function suggest($term = null): array
    {
        $results = $this->agent->all();
        if($term) {
            $results = $results->filter(function ($item, $key) use ($term) {
                return stristr($item->name, $term) || stristr($item->mobile, $term);
            });
        }

        return $results->map(function($item, $key) {
            return [
                'id' => $item->id,
                'name' => $item->name
            ];
        })->toArray();
    }

    public function getBookings($agent_id, $data): array
    {
        $query = BookingItem::with(['booking', 'mapping.cabinType'])
            ->whereHas('booking', function($q) use($agent_id) {
                $q->where('user_id', $agent_id);
            });
        $total = $query->count();
        $bookings = $query->get();
        return [
            'draw' => request()->get('draw'),
            'recordsTotal' => $total,
            'recordsFiltered' => $total,
            'data' => $bookings->toArray()
        ];
    }

    public function getCommissions($agent_id, $data): array
    {
        $response = $this->agent->getCommissions($agent_id, $data);
        return [
            'draw' => request()->get('draw'),
            'recordsTotal' => $response['total'],
            'recordsFiltered' => $response['total'],
            'data' => $response['data']
        ];
    }

    public function dailyCommissions($agent_id, array $params): JsonResponse
    {
        return response()->json([
           'success' => true,
           'message' => '',
           'data' =>  $this->agent->getCommissions($agent_id, $params)->toArray()
        ]);
    }
}
