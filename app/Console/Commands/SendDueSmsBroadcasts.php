<?php

namespace App\Console\Commands;

use App\Services\Sms\SmsBroadcastService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('sms:send-due-broadcasts')]
#[Description('Start every scheduled SMS broadcast whose send time has come')]
class SendDueSmsBroadcasts extends Command
{
    public function handle(SmsBroadcastService $broadcasts): int
    {
        $started = $broadcasts->startDue();

        if ($started) {
            $this->info("Started {$started} broadcast(s).");
        }

        return self::SUCCESS;
    }
}
