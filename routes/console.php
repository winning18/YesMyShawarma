<?php

use App\Console\Commands\AbandonPendingPaymentOrders;
use App\Console\Commands\EscalateUnacknowledgedOrders;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command(EscalateUnacknowledgedOrders::class)->everyMinute();
Schedule::command(AbandonPendingPaymentOrders::class)->everyFiveMinutes();
