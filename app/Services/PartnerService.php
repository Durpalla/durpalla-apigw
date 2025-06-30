<?php


namespace App\Services;


use App\Constants\AppConst;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\Models\BookingItem;
use App\Models\Partner;
use App\Models\AgentIncentive;
use App\Models\UserMeta;
use App\Models\Vehicle;
use App\Repository\Interfaces\AgentRepositoryInterface;
use App\Repository\Interfaces\PartnerRepositoryInterface;

class PartnerService
{
    /**
     * @var PartnerRepositoryInterface
     */
    private $partner;
    private $calculation;
    private $agent;

    public function __construct(
        PartnerRepositoryInterface $partnerRepository,
        CalculationService $calculationService,
        AgentRepositoryInterface $agentRepository
    )
    {
        $this->partner = $partnerRepository;
        $this->calculation = $calculationService;
        $this->agent = $agentRepository;
    }

    public function getIndex(array $data): JsonResponse
    {
        $partners = $this->partner->all();
        if (array_key_exists('status', $data)) {
            $agents = $partners->where('status', $data['status']);
        }
        $total = $partners->count();
        $incentive_types = config('constants.incentive_types');
        $statuses = config('constants.user_status');
        return response()->json([
            'draw' => request()->get('draw'),
            'recordsTotal' => $total,
            'recordsFiltered' => $total,
            'data' => $partners->map(function ($item, $key) use ($incentive_types, $statuses) {
                return [
                    'id' => $item->id,
                    'name' => $item->name,
                    'email' => $item->email,
                    'mobile' => $item->mobile,
                    'address' => ($item->meta) ? $item->meta['address'] : null,
                    'photo' => ($item->profile_pic) ? asset($item->profile_pic) : asset('default/avatar.png'),
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
            DB::transaction(function () use ($data) {
                $partner = Partner::create([
                        'password' => Hash::make($data['password']),
                        'type' => AppConst::PARTNER_TYPE,
                        'email_verified_at' => now(),
                        'status' => AppConst::USER_ACTIVE
                    ] + $data);
                UserMeta::create($data + [
                        'user_id' => $partner->id,
                        'created_by' => auth()->user()->id,
                        'platform' => 'counter'
                    ]);
                AgentIncentive::create($data + ['agent_id' => $partner->id]);
            }, 2);
            return true;
        } catch (\Throwable $exception) {
            return false;
        }
    }

    public function update(array $data, int $id): bool
    {
        try {
            DB::transaction(function () use ($data, $id) {
                if (!empty($data['password'])) {
                    $data = $data + ['password' => Hash::make($data['password'])];
                }
                $this->partner->update($data, $id);
            }, 2);
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function suggest($term = null): array
    {
        $results = $this->partner->all();
        if ($term) {
            $results = $results->filter(function ($item, $key) use ($term) {
                return stristr($item->name, $term) || stristr($item->mobile, $term);
            });
        }

        return $results->map(function ($item, $key) {
            return [
                'id' => $item->id,
                'name' => $item->name
            ];
        })->toArray();
    }

    public function getBookings($agent_id, $data): array
    {
        $query = BookingItem::with(['booking', 'mapping.cabinType'])
            ->whereHas('booking', function ($q) use ($agent_id) {
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
            'data' => $response['bookings']->toArray()
        ];
    }

    public function dailyCommissions($agent_id, array $params): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => '',
            'data' => $this->agent->getCommissions($agent_id, $params)->toArray()
        ]);
    }

    public function suggestVehicle($term)
    {
        $query = Vehicle::doesnthave('partners');
        if ($term) {
            $query->where('name', 'LIKE', '%' . $term . '%');
        }

        $results = $query->get();

        return $results->map(function ($item, $key) {
            return [
                'id' => $item->id,
                'name' => $item->name
            ];
        })->toArray();
    }
}
