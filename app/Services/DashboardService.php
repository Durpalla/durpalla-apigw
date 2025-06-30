<?php


namespace App\Services;


use App\Constants\AppConst;
use App\Repository\Interfaces\MerchantRepositoryInterface;

class DashboardService
{
    private $merchantRepository;
    private $merchants;
    private $vehicles;
    public function __construct( MerchantRepositoryInterface $merchantRepository)
    {
        $this->merchantRepository = $merchantRepository;
        $this->merchants = $merchantRepository->all();
    }

    public function merchantCount()
    {
        return $this->merchants->count();
    }

    public function launchCount()
    {
        $this->vehicles = $this->merchants->flatMap(function($item, $key) {
            return $item->vehicles;
        });
        return $this->vehicles->where('status', AppConst::LAUNCH_ACTIVE)->count();
    }

    public function cabinsCount()
    {
        return $this->vehicles->flatMap(function($item, $key) {
            return $item->cabins;
        })->count();
    }

    public function seatsCount()
    {
        return $this->vehicles->flatMap(function($item, $key) {
            return $item->seats;
        })->count();
    }

    public function decksCount()
    {
        return $this->vehicles->sum('passengers_capacity');
    }

    public function currentTrips()
    {
        return $this->vehicles->flatMap(function($item, $key) {
            return $item->activeTrips->where('schedule_date', date('Y-m-d'));
        })->sortBy('leaving_at')->take(10);
    }
}
