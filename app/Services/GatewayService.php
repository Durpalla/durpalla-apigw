<?php


namespace App\Services;

use App\Constants\AppConst;
use Illuminate\Support\Collection;
use App\Repository\Interfaces\GatewayRepositoryInterface;
use Illuminate\Support\Facades\Cache;
use App\Models\Gateway;

class GatewayService
{
    private $repository;

    public function __construct(GatewayRepositoryInterface $gatewayRepository)
    {
        $this->repository = $gatewayRepository;
    }

    public function all()
    {
        return Cache::remember('gateways', 3600, function () {
            return Gateway::where('status', 1)->get();
        });
    }

    public function getActive(): Collection
    {
        return $this->repository->all()->where('status', AppConst::GATEWAY_ACTIVE);
    }
}
