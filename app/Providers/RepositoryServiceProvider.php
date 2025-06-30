<?php

namespace App\Providers;

use App\Repository\AgentPaymentMethodRepository;
use App\Repository\AgentRepository;
use App\Repository\BookingItemRepository;
use App\Repository\BaseRepository;
use App\Repository\BookingRepository;
use App\Repository\CabinTypeRepository;
use App\Repository\CancellationRepository;
use App\Repository\CommissionRepository;
use App\Repository\CouponRepository;
use App\Repository\GatewayRepository;
use App\Repository\GhatRepository;
use App\Repository\Interfaces\AgentPaymentMethodRepositoryInterface;
use App\Repository\Interfaces\AgentRepositoryInterface;
use App\Repository\Interfaces\BaseRepositoryInterface;
use App\Repository\Interfaces\BookingItemRepositoryInterface;
use App\Repository\Interfaces\BookingRepositoryInterface;
use App\Repository\Interfaces\CabinTypeRepositoryInterface;
use App\Repository\Interfaces\CancellationRepositoryInterface;
use App\Repository\Interfaces\CommissionRepositoryInterface;
use App\Repository\Interfaces\CouponRepositoryInterface;
use App\Repository\Interfaces\GatewayRepositoryInterface;
use App\Repository\Interfaces\GhatRepositoryInterface;
use App\Repository\Interfaces\PartnerRepositoryInterface;
use App\Repository\Interfaces\ScheduleCabinMappingRepositoryInterface;
use App\Repository\Interfaces\VehicleRepositoryInterface;
use App\Repository\Interfaces\MerchantRepositoryInterface;
use App\Repository\Interfaces\OptionRepositoryInterface;
use App\Repository\Interfaces\PartyRepositoryInterface;
use App\Repository\Interfaces\RouteRepositoryInterface;
use App\Repository\Interfaces\ScheduleRepositoryInterface;
use App\Repository\Interfaces\ServiceRepositoryInterface;
use App\Repository\Interfaces\UserRepositoryInterface;
use App\Repository\Interfaces\WithdrawalRepositoryInterface;
use App\Repository\PartnerRepository;
use App\Repository\ScheduleCabinMappingRepository;
use App\Repository\VehicleRepository;
use App\Repository\MerchantRepository;
use App\Repository\OptionRepository;
use App\Repository\PartyRepository;
use App\Repository\RouteRepository;
use App\Repository\ScheduleRepository;
use Illuminate\Support\ServiceProvider;
use App\Repository\ServiceRepository;
use App\Repository\UserRepository;
use App\Repository\WithdrawalRepository;

class RepositoryServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->bind(BaseRepositoryInterface::class, BaseRepository::class);
        $this->app->bind(VehicleRepositoryInterface::class, VehicleRepository::class);
        $this->app->bind(ScheduleRepositoryInterface::class, ScheduleRepository::class);
        $this->app->bind(BookingRepositoryInterface::class, BookingRepository::class);
        $this->app->bind(UserRepositoryInterface::class, UserRepository::class);
        $this->app->bind(OptionRepositoryInterface::class, OptionRepository::class);
        $this->app->bind(CancellationRepositoryInterface::class, CancellationRepository::class);
        $this->app->bind(MerchantRepositoryInterface::class, MerchantRepository::class);
        $this->app->bind(RouteRepositoryInterface::class, RouteRepository::class);
        $this->app->bind(CabinTypeRepositoryInterface::class, CabinTypeRepository::class);
        $this->app->bind(GhatRepositoryInterface::class, GhatRepository::class);
        $this->app->bind(CouponRepositoryInterface::class, CouponRepository::class);
        $this->app->bind(BookingItemRepositoryInterface::class, BookingItemRepository::class);
        $this->app->bind(PartyRepositoryInterface::class, PartyRepository::class);
        $this->app->bind(ServiceRepositoryInterface::class, ServiceRepository::class);
        $this->app->bind(ScheduleCabinMappingRepositoryInterface::class, ScheduleCabinMappingRepository::class);
        $this->app->bind(AgentRepositoryInterface::class, AgentRepository::class);
        $this->app->bind(CommissionRepositoryInterface::class, CommissionRepository::class);
        $this->app->bind(WithdrawalRepositoryInterface::class, WithdrawalRepository::class);
        $this->app->bind(AgentPaymentMethodRepositoryInterface::class, AgentPaymentMethodRepository::class);
        $this->app->bind(GatewayRepositoryInterface::class, GatewayRepository::class);
        $this->app->bind(PartnerRepositoryInterface::class, PartnerRepository::class);
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        //
    }
}
