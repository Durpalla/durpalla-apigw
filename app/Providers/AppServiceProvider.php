<?php

namespace App\Providers;

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Laravel\Octane\RoadRunner\ServerProcessInspector as OctaneRoadRunnerServerProcessInspector;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        if (class_exists(OctaneRoadRunnerServerProcessInspector::class)) {
            $this->app->bind(
                OctaneRoadRunnerServerProcessInspector::class,
                \App\Octane\RoadRunner\ServerProcessInspector::class
            );
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Schema::defaultStringLength(191);
    }
}
