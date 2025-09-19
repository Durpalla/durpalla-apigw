<?php


namespace App\Providers;


use Illuminate\Support\ServiceProvider;
use App\Gateways\Bkash;
use App\Gateways\GatewayInterface;
use App\Gateways\Nagad;
use App\Gateways\Sslcom;

class PaymentServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        $this->app->bind(GatewayInterface::class, Bkash::class);
        $this->app->bind(GatewayInterface::class, Nagad::class);
        $this->app->bind(GatewayInterface::class, Sslcom::class);
    }
}
