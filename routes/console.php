<?php

use App\Console\Commands\AbandonPendingPaymentOrders;
use App\Console\Commands\ApplyMenuItemSchedules;
use App\Console\Commands\DeleteExpiredDamageReportPhotos;
use App\Console\Commands\DeleteExpiredMarketListReceipts;
use App\Console\Commands\EscalateUnacknowledgedOrders;
use App\Console\Commands\ExpiryAlertsCommand;
use App\Console\Commands\SendDueSmsBroadcasts;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command(EscalateUnacknowledgedOrders::class)->everyMinute();
// A pharmacy's expiry SMS to the owner (R5): Monday mornings, Accra time.
Schedule::command(ExpiryAlertsCommand::class)->weeklyOn(1, '08:00')->timezone('Africa/Accra');
Schedule::command(AbandonPendingPaymentOrders::class)->everyFiveMinutes();
Schedule::command(ApplyMenuItemSchedules::class)->everyFiveMinutes();
Schedule::command(DeleteExpiredDamageReportPhotos::class)->hourly();
Schedule::command(DeleteExpiredMarketListReceipts::class)->hourly();
Schedule::command(SendDueSmsBroadcasts::class)->everyMinute()->withoutOverlapping();
