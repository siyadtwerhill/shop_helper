<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Console\Commands\ExpireSubscriptions;
use App\Console\Commands\ExpirePendingPayments;
use App\Console\Commands\ApplyScheduledDowngrades;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command(ExpireSubscriptions::class)->dailyAt('01:00')->withoutOverlapping();
Schedule::command(ExpirePendingPayments::class)->hourly();
Schedule::command(ApplyScheduledDowngrades::class)->dailyAt('01:30');
