<?php

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('schedule:run', function (Schedule $schedule) {
    $schedule->command('stoppage:update')
        ->daily()
        ->environments(['production'])
        ->runInBackground()
        ->after(function() {
            Log::debug('Updated stoppages');
        });

    $schedule->command('release:lock')
        ->everyFifteenMinutes()
        ->environments(['staging', 'production'])
        ->runInBackground()
        ->before(function () {
            Log::debug('Releasing lock items');
        })
        ->after(function () {
            Log::debug('Lock items released');
        });

    $schedule->command('trip:update')
        ->environments(['staging', 'production'])
        ->everyMinute();

    $schedule->command('booking:pending')
        ->everyFifteenMinutes()
        ->environments(['staging', 'production'])
        ->runInBackground()
        ->before(function () {
            Log::debug('Handling pending bookings');
        })
        ->after(function () {
            Log::debug('Pending booking handled');
        });
});
