<?php

namespace App\Services;

use App\Constants\AppConst;
use App\Models\Gateway;
use App\Repository\Interfaces\GatewayRepositoryInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class GatewayService
{
    private $repository;

    public function __construct(GatewayRepositoryInterface $gatewayRepository)
    {
        $this->repository = $gatewayRepository;
    }

    /**
     * Public customer gateways: platform live + for_public.
     */
    public function all()
    {
        return Cache::remember('gateways.public.live', 600, function () {
            if (Schema::hasColumn('gateways', 'channel')) {
                return Gateway::query()
                    ->where('status', 1)
                    ->whereNull('merchant_id')
                    ->where('channel', 'live')
                    ->where('for_public', true)
                    ->orderBy('sort_order')
                    ->orderBy('name')
                    ->get();
            }

            return Gateway::where('status', 1)->get();
        });
    }

    public function getActive(): Collection
    {
        if (Schema::hasColumn('gateways', 'channel')) {
            return Gateway::query()
                ->where('status', AppConst::GATEWAY_ACTIVE)
                ->whereNull('merchant_id')
                ->where('channel', 'live')
                ->where('for_public', true)
                ->orderBy('sort_order')
                ->get();
        }

        return $this->repository->all()->where('status', AppConst::GATEWAY_ACTIVE);
    }
}
