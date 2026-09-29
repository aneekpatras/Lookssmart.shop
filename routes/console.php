<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Brief §5 / Phase 4 item 10: encrypted daily backups + failure alerting (spatie/laravel-backup).
// `withoutOverlapping()` guards against a slow backup still running when the next day's fires.
Schedule::command('backup:run')->daily()->at('02:00')->withoutOverlapping();
Schedule::command('backup:clean')->daily()->at('01:30')->withoutOverlapping();
Schedule::command('backup:monitor')->daily()->at('03:00')->withoutOverlapping();

// Phase 8 item 2: staff daily schedule digest, sent before the salon opens.
Schedule::command('app:send-staff-daily-schedules')->dailyAt('07:00')->withoutOverlapping();
Schedule::command('bookings:send-reminders')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('bookings:request-reviews')->dailyAt('10:00')->withoutOverlapping();

// Phase 10 sub-step 2: promote scheduled blog posts once their scheduled time passes.
Schedule::command('posts:publish-scheduled')->everyFiveMinutes()->withoutOverlapping();
