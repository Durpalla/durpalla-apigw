<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// NOTE: these must be top-level Schedule::command() calls (the modern Laravel
// 11+ way), not wrapped inside an `Artisan::command('schedule:run', ...)`
// closure. That pattern only *registers* events on the Schedule instance
// when the closure runs - it never calls dueEvents()->run(), so overriding
// the "schedule:run" command name that way silently no-ops every task that
// schedule:work invokes each minute.
Schedule::command('stoppage:update')
    ->daily()
    ->environments(['production'])
    ->runInBackground()
    ->after(function () {
        Log::debug('Updated stoppages');
    });

Schedule::command('release:lock')
    ->everyFiveMinutes()
    ->runInBackground()
    ->before(function () {
        Log::debug('Releasing lock items');
    })
    ->after(function () {
        Log::debug('Lock items released');
    });

Schedule::command('trip:update')
    ->environments(['staging', 'production'])
    ->everyMinute();

Schedule::command('booking:pending')
    ->everyMinute()
    ->runInBackground()
    ->before(function () {
        Log::debug('Handling pending bookings');
    })
    ->after(function () {
        Log::debug('Pending booking handled');
    });

Schedule::command('hotel:maintain')
    ->everyMinute()
    ->runInBackground();

// Credits agent commissions once the trip window ends, so cancelled or
// refunded seats never earn (see AgentJourneyCommissionService).
Schedule::command('commission:journey-complete')
    ->everyFifteenMinutes()
    ->runInBackground()
    ->withoutOverlapping();

// Re-open bookings that were marked checked but are missing expected accruals
// (e.g. dual booker/referrer gaps, locked zero-accrual hotel bookings).
Schedule::command('commission:repair-missing --hours=1 --limit=100')
    ->hourly()
    ->runInBackground()
    ->withoutOverlapping();
