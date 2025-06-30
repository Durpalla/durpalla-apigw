<?php


namespace App\Repository;


use App\Constants\AppConst;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use App\Models\Merchant;
use App\Repository\Interfaces\MerchantRepositoryInterface;

class MerchantRepository extends BaseRepository implements MerchantRepositoryInterface
{
    public function __construct(Merchant $model)
    {
        parent::__construct($model);
    }

    public function all() : Collection
    {
        Cache::forget('merchants');
        return Cache::remember('merchants', 120, function() {
            return parent::with(['vehicles.cabins', 'vehicles.seats', 'vehicles.activeTrips.route', 'vehicles.activeTrips.launch'])->get();
        });
    }
}
