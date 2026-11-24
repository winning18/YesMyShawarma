<?php

namespace App\Console\Commands;

use App\Services\MarketLists\MarketListService;
use App\Services\Modules\ModuleService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Deletes market-list receipt photos 24 hours after a reviewer viewed them
 * and the list was approved (owner, 2026-11-24; MarketListService::
 * deleteExpiredReceipts()). The list itself stays. Hourly, like the damage
 * report photos; nothing runs while the Market list module is off.
 */
#[Signature('market-lists:delete-expired-receipts')]
#[Description('Delete receipt photos 24h after they were viewed and the list approved')]
class DeleteExpiredMarketListReceipts extends Command
{
    public function handle(MarketListService $marketLists, ModuleService $modules): int
    {
        if (! $modules->enabled(ModuleService::MARKET_LIST)) {
            return self::SUCCESS;
        }

        $deleted = $marketLists->deleteExpiredReceipts();

        if ($deleted > 0) {
            $this->info("Deleted {$deleted} expired receipt photo(s).");
        }

        return self::SUCCESS;
    }
}
