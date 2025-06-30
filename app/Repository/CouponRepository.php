<?php


namespace App\Repository;


use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use App\Models\Coupon;
use App\Repository\Interfaces\CouponRepositoryInterface;

class CouponRepository extends BaseRepository implements CouponRepositoryInterface
{
    public function __construct(Coupon $model)
    {
        parent::__construct($model);
    }

    public function all() : Collection
    {
        return Cache::remember('coupons', 120, function() {
            return parent::all();
        });
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
