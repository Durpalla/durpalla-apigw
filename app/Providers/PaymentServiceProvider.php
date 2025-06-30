<?php


namespace App\Providers;


use Illuminate\Support\ServiceProvider;
use App\Gateway\Bkash;
use App\Gateway\GatewayInterface;
use App\Gateway\Nagad;
use App\Gateway\Sslcom;

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
