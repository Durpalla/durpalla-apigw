<?php


namespace App\Services;


use App\Constants\AppConst;
use Illuminate\Support\Collection;
use App\Repository\Interfaces\GatewayRepositoryInterface;

class GatewayService
{
    private $repository;

    public function __construct(GatewayRepositoryInterface $gatewayRepository)
    {
        $this->repository = $gatewayRepository;
    }

    public function getActive(): Collection
    {
        return $this->repository->all()->where('status', AppConst::GATEWAY_ACTIVE);
    }
}
