<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('app:daily-tasks')->dailyAt('03:00');

// Offset from the 03:00 daily-tasks run so a slow mysqldump never delays those
// time-sensitive reminders/renewals, and vice versa.
Schedule::command('app:backup-database')->dailyAt('03:30');
