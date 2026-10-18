<?php

use App\Console\Commands\AbandonPendingPaymentOrders;
use App\Console\Commands\ApplyMenuItemSchedules;
use App\Console\Commands\DeleteExpiredDamageReportPhotos;
use App\Console\Commands\EscalateUnacknowledgedOrders;
use App\Console\Commands\SendDueSmsBroadcasts;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command(EscalateUnacknowledgedOrders::class)->everyMinute();
Schedule::command(AbandonPendingPaymentOrders::class)->everyFiveMinutes();
Schedule::command(ApplyMenuItemSchedules::class)->everyFiveMinutes();
Schedule::command(DeleteExpiredDamageReportPhotos::class)->hourly();
Schedule::command(SendDueSmsBroadcasts::class)->everyMinute()->withoutOverlapping();
