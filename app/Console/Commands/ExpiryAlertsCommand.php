<?php

namespace App\Console\Commands;

use App\Jobs\SendSms;
use App\Models\Branch;
use App\Services\Branches\BranchContext;
use App\Services\Settings\BusinessType;
use App\Services\Stock\BatchService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * A pharmacy's weekly expiry SMS to the owner (R5, 2026-11-27; how often and
 * how far ahead to confirm with the pilot pharmacist): per branch, how many
 * batches have expired and how many expire within the warning days.
 */
#[Signature('expresspos:expiry-alerts')]
#[Description('Text the owner of a pharmacy about expired and soon-to-expire batches')]
class ExpiryAlertsCommand extends Command
{
    public function handle(BusinessType $type, BatchService $batches, BranchContext $context): int
    {
        if ($type->current() !== BusinessType::PHARMACY) {
            return self::SUCCESS;
        }

        $today = now('Africa/Accra')->startOfDay();
        foreach (Branch::where('is_active', true)->get() as $branch) {
            $due = $batches->expiring($branch->id);
            if ($due->isEmpty()) {
                continue;
            }
            $expired = $due->filter(fn ($b) => $b->expiry_date->lt($today))->count();
            $message = __('Expiry at :branch: :soon batches expire within :days days, :expired already expired. See Stock, Expiry.', [
                'branch' => $branch->name, 'soon' => $due->count() - $expired, 'days' => $batches->warningDays(), 'expired' => $expired,
            ]);

            foreach ($context->usersWithRole('owner', null) as $owner) {
                if ($owner->phone) {
                    SendSms::dispatch($owner->phone, $message, ['branch_id' => $branch->id]);
                }
            }
            $this->line($message);
        }

        return self::SUCCESS;
    }
}
