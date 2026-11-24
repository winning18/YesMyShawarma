<?php

namespace App\Console\Commands;

use App\Services\DamageReports\DamageReportService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Deletes a damage report's photo 24h after a reviewer first viewed it
 * (DamageReportService::markPhotoViewed()) and the report was approved or
 * denied, whichever came later (owner, 2026-11-24) — the report row itself
 * stays permanently for audit, only the file goes. A pending report, or
 * one whose photo nobody has viewed, is never touched here.
 * Hourly, not everyMinute() like orders:escalate-unacknowledged — nothing
 * about a 24h deadline needs minute-level precision.
 */
#[Signature('damage-reports:delete-expired-photos')]
#[Description('Delete damage report photos 24h after they were viewed and the report decided')]
class DeleteExpiredDamageReportPhotos extends Command
{
    public function handle(DamageReportService $damageReports): int
    {
        $deleted = $damageReports->deleteExpiredPhotos();

        if ($deleted > 0) {
            $this->info("Deleted {$deleted} expired damage report photo(s).");
        }

        return self::SUCCESS;
    }
}
