<?php


namespace App\Providers;


use Illuminate\Support\ServiceProvider;
use App\Gateways\Bkash;
use App\Gateways\GatewayInterface;

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
    public function boot(): void
    {
        // Customer payment/make resolves the handler from gateways.class_name (Bkash / Nagad) via CommonHelper::purseGateway().
        // This default is only for services that type-hint GatewayInterface without a concrete gateway (e.g. App\Services\Payment).
        $this->app->bind(GatewayInterface::class, Bkash::class);
    }
}
