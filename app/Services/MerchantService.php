<?php


namespace App\Services;


use Illuminate\Support\Facades\Cache;
use App\Repository\Interfaces\MerchantRepositoryInterface;

class MerchantService
{
    private $merchant;
    public function __construct(MerchantRepositoryInterface $merchantRepository)
    {
        $this->merchant = $merchantRepository;
    }

    public function getDropDown()
    {
        return Cache::rememberForever('merchant_dropdowns', function() {
            return $this->merchant->all()->pluck('merchant_name', 'user_id');
        });
    }
}
