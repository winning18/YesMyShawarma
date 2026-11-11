<?php

namespace App\Services\Delivery;

use App\Exceptions\DeliveryFeeCollectionException;
use App\Models\DeliveryFeeCollection;
use App\Models\User;
use App\Services\Reports\DeliveryFeeReportService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Riders hand in the cash delivery fees they collect at the door (owner,
 * 2026-11-11); before the hand-in start date every fee was theirs to keep.
 * Prepaid fees (Paystack/MoMo) are already with the business, so there's
 * nothing to collect for them.
 *
 * A collection is one rider, one branch (the cash is handed in at a
 * branch), one period: every one of that rider's still-uncollected cash
 * fees there in those dates. The amount actually handed in is recorded
 * beside what was expected, so a shortfall shows. Each order goes into
 * one live collection at most; voiding (a mistake, with a reason) frees
 * its orders again and keeps the record.
 */
class DeliveryFeeCollectionService
{
    public function __construct(private readonly DeliveryFeeReportService $fees) {}

    /**
     * What this rider owes at this branch for these dates.
     *
     * @return array{rows: Collection<int, array<string, mixed>>, expected: int, count: int}
     */
    public function owed(User $rider, int $branchId, Carbon $from, Carbon $to): array
    {
        $rows = $this->fees->rows($from, $to, $rider->id, branchId: $branchId)
            ->where('collection_status', DeliveryFeeReportService::STATUS_TO_COLLECT)
            ->values();

        return ['rows' => $rows, 'expected' => (int) $rows->sum('cash'), 'count' => $rows->count()];
    }

    public function record(User $collector, User $rider, int $branchId, Carbon $from, Carbon $to, int $received, ?string $note = null): DeliveryFeeCollection
    {
        $owed = $this->owed($rider, $branchId, $from, $to);

        if ($owed['count'] === 0) {
            throw DeliveryFeeCollectionException::nothingToCollect();
        }

        try {
            return DB::transaction(function () use ($collector, $rider, $branchId, $from, $to, $received, $note, $owed) {
                $collection = DeliveryFeeCollection::create([
                    'branch_id' => $branchId,
                    'rider_id' => $rider->id,
                    'period_from' => $from->toDateString(),
                    'period_to' => $to->toDateString(),
                    'expected' => $owed['expected'],
                    'received' => $received,
                    'order_count' => $owed['count'],
                    'note' => $note,
                    'collected_by' => $collector->id,
                    'collected_at' => now(),
                ]);

                $collection->orders()->createMany($owed['rows']->map(fn (array $row) => [
                    'order_id' => $row['order_id'],
                    'cash_fee' => $row['cash'],
                ])->all());

                return $collection;
            });
        } catch (UniqueConstraintViolationException) {
            throw DeliveryFeeCollectionException::alreadyCollected();
        }
    }

    public function void(DeliveryFeeCollection $collection, User $user, string $reason): DeliveryFeeCollection
    {
        if ($collection->voided_at !== null) {
            throw DeliveryFeeCollectionException::alreadyVoided();
        }

        DB::transaction(function () use ($collection, $user, $reason) {
            $collection->orders()->delete();
            $collection->update(['voided_at' => now(), 'voided_by' => $user->id, 'void_reason' => $reason]);
        });

        return $collection->fresh();
    }
}
