<?php

namespace App\Providers;

use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class ViewComposerServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        // View::composer(
        //     '*', 'App\Http\View\Composers\MerchantComposer'
        // );
        // View::composer(
        //     '*', 'App\Http\View\Composers\RouteComposer'
        // );
        // View::composer(
        //     '*', 'App\Http\View\Composers\CabinTypeComposer'
        // );
        // View::composer(
        //     '*', 'App\Http\View\Composers\GhatComposer'
        // );
        // View::composer(
        //     '*', 'App\Http\View\Composers\PartyComposer'
        // );
        // View::composer(
        //     '*', 'App\Http\View\Composers\ServiceComposer'
        // );
        // View::composer(
        //     '*', 'App\Http\View\Composers\GatewayComposer'
        // );
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
