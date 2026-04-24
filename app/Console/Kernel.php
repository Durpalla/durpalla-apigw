<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use Illuminate\Support\Facades\Log;

class Kernel extends ConsoleKernel
{
    protected function schedule(Schedule $schedule): void
    {
        $schedule->command('stoppage:update')
            ->daily()
//            ->environments(['production'])
            ->runInBackground()
            ->after(function() {
                Log::debug('Updated stoppages');
            });

        $schedule->command('release:lock')
            ->everyFiveMinutes()
            ->runInBackground()
            ->before(function () {
                Log::debug('Releasing lock items');
            })
            ->after(function () {
                Log::debug('Lock items released');
            });

        $schedule->command('trip:update')
            ->everyMinute();
//            ->environments(['staging', 'production']);

        $schedule->command('booking:pending')
            ->everyMinute()
            ->runInBackground()
            ->before(function () {
                Log::debug('Handling pending bookings');
            })
            ->after(function () {
                Log::debug('Pending booking handled');
            });

        $schedule->command('hotel:maintain')
            ->everyMinute()
            ->runInBackground();
    }

    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
