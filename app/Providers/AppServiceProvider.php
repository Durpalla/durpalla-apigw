<?php

namespace App\Providers;

use App\Models\VehicleSchedule;
use App\Observers\VehicleScheduleObserver;
use App\Redis\PredisSentinelConnector;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Laravel\Passport\Passport;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->registerDurpallaMigrationsForTesting();
    }

    /**
     * API gateway has no local migrations; tests use RefreshDatabase against apigw_test.
     * Load Durpalla core migrations plus minimal Hotel-module files required for FKs from
     * core migrations (e.g. suppliers before supplier_vehicles).
     *
     * @see .cursor/rules/schema-in-durpalla-app.mdc
     */
    private function registerDurpallaMigrationsForTesting(): void
    {
        if (! $this->app->environment('testing')) {
            return;
        }

        $paths = $this->durpallaTestingMigrationPaths();
        if ($paths !== []) {
            $this->loadMigrationsFrom($paths);
        }
    }

    /**
     * @return list<string>
     */
    private function durpallaTestingMigrationPaths(): array
    {
        $overrideRoot = env('DURPALLA_APP_PATH');
        $root = is_string($overrideRoot) && $overrideRoot !== '' && is_dir($overrideRoot)
            ? rtrim($overrideRoot, DIRECTORY_SEPARATOR)
            : dirname((string) base_path()).DIRECTORY_SEPARATOR.'durpalla';

        $paths = [];

        if (is_dir($root)) {
            $main = $root.DIRECTORY_SEPARATOR.'database'.DIRECTORY_SEPARATOR.'migrations';
            if (is_dir($main)) {
                $paths[] = $main;
            }

            /*
             * Do not register every Modules subdirectory Database/Migrations path: that loads a legacy
             * Module/Hotel hotels schema that conflicts with core 2026_04_24_* hotel tables,
             * duplicate Chatbot paths (same migration basename), and greatly slows CI.
             * Core migrations reference suppliers from Module/Hotel; include only those deps.
             */
            $hotelDir = $root.DIRECTORY_SEPARATOR.'Modules'.DIRECTORY_SEPARATOR.'Hotel'.DIRECTORY_SEPARATOR.'Database'.DIRECTORY_SEPARATOR.'Migrations';
            foreach ([
                '2026_01_28_000004_create_cities_table.php',
                '2026_01_28_000005_create_suppliers_table.php',
            ] as $basename) {
                $file = $hotelDir.DIRECTORY_SEPARATOR.$basename;
                if (is_file($file)) {
                    $paths[] = $file;
                }
            }
        }

        if ($paths === []) {
            $legacy = env('DURPALLA_MAIN_MIGRATIONS_PATH');
            if (is_string($legacy) && $legacy !== '' && is_dir($legacy)) {
                return [$legacy];
            }
        }

        return $paths;
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if (is_readable(storage_path('oauth-private.key'))
            && is_readable(storage_path('oauth-public.key'))) {
            Passport::loadKeysFrom(storage_path());
        }

        Redis::extend('predis', fn () => new PredisSentinelConnector);

        Schema::defaultStringLength(191);
        VehicleSchedule::observe(VehicleScheduleObserver::class);

        if ($this->app->environment('production', 'staging')) {
            $appUrl = rtrim((string) config('app.url'), '/');
            if (str_starts_with($appUrl, 'https://')) {
                URL::forceScheme('https');
                URL::forceRootUrl($appUrl);
            }
        }

        RateLimiter::for('auth', function (Request $request) {
            $key = $request->ip().'|'.strtolower((string) $request->input('mobile', $request->input('email', '')));

            return Limit::perMinute(10)->by($key);
        });

        RateLimiter::for('otp', function (Request $request) {
            $key = $request->ip().'|'.strtolower((string) $request->input('mobile', ''));

            return Limit::perMinute(5)->by($key);
        });
    }
}
