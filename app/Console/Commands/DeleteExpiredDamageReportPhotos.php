<?php

namespace App\Console\Commands;

use App\Services\DamageReports\DamageReportService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Deletes a damage report's photo once it's been more than 24h since a
 * reviewer first viewed it (DamageReportService::markPhotoViewed()) — the
 * report row itself stays permanently for audit, only the file goes. A
 * report whose photo has never been viewed is never touched here, however
 * old it gets; the 24h window only starts once someone's actually looked.
 * Hourly, not everyMinute() like orders:escalate-unacknowledged — nothing
 * about a 24h deadline needs minute-level precision.
 */
#[Signature('damage-reports:delete-expired-photos')]
#[Description('Delete damage report photos more than 24h past their first review view')]
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
