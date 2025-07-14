<?php

namespace App\Providers;

use App\Http\View\Composers\CabinTypeComposer;
use App\Http\View\Composers\GatewayComposer;
use App\Http\View\Composers\GhatComposer;
use App\Http\View\Composers\MerchantComposer;
use App\Http\View\Composers\PartyComposer;
use App\Http\View\Composers\RouteComposer;
use App\Http\View\Composers\ServiceComposer;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class ViewComposerServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register(): void
    {
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot(): void
    {
        View::composer(
            '*', MerchantComposer::class
        );
        View::composer(
            '*', RouteComposer::class
        );
        View::composer(
            '*', CabinTypeComposer::class
        );
        View::composer(
            '*', GhatComposer::class
        );
        View::composer(
            '*', PartyComposer::class
        );
        View::composer(
            '*', ServiceComposer::class
        );
        View::composer(
            '*', GatewayComposer::class
        );
    }
}
