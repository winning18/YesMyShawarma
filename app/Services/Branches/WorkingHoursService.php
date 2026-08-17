<?php

namespace App\Services\Branches;

use App\Models\Branch;
use App\Models\BranchWorkingHour;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Weekly opening schedule, distinct from Branch::opens_at/closes_at (a
 * single daily display value shown on the customer-facing branches page,
 * purely informational — see OrderCreationService). This is the per-day-
 * of-week schedule behind the "Working Hours" tab, and what actually
 * gates order placement.
 */
class WorkingHoursService
{
    /**
     * All 7 days, 1 (Monday) through 7 (Sunday), filled with null
     * opens_at/closes_at for any day the branch hasn't configured yet —
     * the edit form always shows a full week, not just the rows that
     * happen to exist.
     *
     * @return Collection<int, array{day_of_week: int, opens_at: ?string, closes_at: ?string}>
     */
    public function forBranch(int $branchId): Collection
    {
        $existing = BranchWorkingHour::where('branch_id', $branchId)->get()->keyBy('day_of_week');

        return collect(range(1, 7))->map(function (int $day) use ($existing) {
            $row = $existing->get($day);

            return [
                'day_of_week' => $day,
                'opens_at' => $row?->opens_at,
                'closes_at' => $row?->closes_at,
            ];
        });
    }

    /**
     * $days is keyed by day_of_week (1-7), each ['opens_at' => ?string,
     * 'closes_at' => ?string] — both null clears the day back to closed.
     * Upserted in one transaction so a partial failure never leaves the
     * week half-saved.
     *
     * @param  array<int, array{opens_at: ?string, closes_at: ?string}>  $days
     */
    public function save(int $branchId, array $days): void
    {
        DB::transaction(function () use ($branchId, $days) {
            foreach ($days as $dayOfWeek => $hours) {
                BranchWorkingHour::updateOrCreate(
                    ['branch_id' => $branchId, 'day_of_week' => $dayOfWeek],
                    [
                        'opens_at' => $this->withSeconds($hours['opens_at'] ?? null),
                        'closes_at' => $this->withSeconds($hours['closes_at'] ?? null),
                    ],
                );
            }
        });
    }

    /**
     * The form submits "H:i" — normalised to "H:i:s" here so the stored
     * value is identical whether the column is a real MySQL TIME (which
     * would coerce it anyway) or SQLite's untyped text storage (which
     * would otherwise keep whatever string was written).
     */
    private function withSeconds(?string $time): ?string
    {
        return $time !== null ? Carbon::createFromFormat('H:i', $time)->format('H:i:s') : null;
    }

    /**
     * Whether $branch is open at $at, per its weekly schedule — the thing
     * that now actually gates ordering (OrderCreationService no longer
     * ignores this; see its docblock). A branch with zero
     * branch_working_hours rows at all — the feature simply never
     * configured — fails open, so a branch that hasn't touched the
     * "Working Hours" tab yet behaves exactly as before this existed.
     *
     * A day whose row DOES exist but has null opens_at/closes_at is a
     * different thing: that's the schema's documented "closed that day"
     * signal (schema.md), not "unconfigured" — deliberately honoured here
     * even though it means a branch with only a couple of days filled in
     * reads as closed the rest of the week. See the caller for why that
     * matters operationally right now.
     */
    public function isOpenAt(Branch $branch, Carbon $at): bool
    {
        $rows = BranchWorkingHour::where('branch_id', $branch->id)->get()->keyBy('day_of_week');

        if ($rows->isEmpty()) {
            return true;
        }

        $local = $at->copy()->timezone('Africa/Accra');
        $time = $local->format('H:i:s');
        $today = (int) $local->isoWeekday();
        $yesterday = $today === 1 ? 7 : $today - 1;

        $todayRow = $rows->get($today);

        if ($todayRow && $todayRow->opens_at && $todayRow->closes_at) {
            if ($todayRow->closes_at > $todayRow->opens_at) {
                // Ordinary same-day window.
                if ($time >= $todayRow->opens_at && $time <= $todayRow->closes_at) {
                    return true;
                }
            } elseif ($time >= $todayRow->opens_at) {
                // closes_at <= opens_at means this window crosses midnight
                // (e.g. opens 18:00, closes 02:00) — this is the evening
                // half, still "today".
                return true;
            }
        }

        $yesterdayRow = $rows->get($yesterday);

        if (
            $yesterdayRow && $yesterdayRow->opens_at && $yesterdayRow->closes_at
            && $yesterdayRow->closes_at <= $yesterdayRow->opens_at
            && $time <= $yesterdayRow->closes_at
        ) {
            // The early-morning tail of yesterday's overnight window.
            return true;
        }

        return false;
    }

    public function isOpenNow(Branch $branch): bool
    {
        return $this->isOpenAt($branch, now());
    }

    /**
     * The next moment $branch opens at/after $from — null if nothing's
     * configured at all (isOpenAt would already say "open" in that case,
     * so this is only ever asked when there genuinely is a schedule to
     * scan) or the branch is closed for the next full week. Scans forward
     * day by day rather than resolving a precise "closed until" instant,
     * since a branch could theoretically be mid-way through being closed
     * for several consecutive days.
     */
    public function nextOpening(Branch $branch, ?Carbon $from = null): ?Carbon
    {
        $from = ($from ?? now())->copy()->timezone('Africa/Accra');
        $rows = BranchWorkingHour::where('branch_id', $branch->id)->get()->keyBy('day_of_week');

        if ($rows->isEmpty()) {
            return null;
        }

        for ($offset = 0; $offset <= 7; $offset++) {
            $candidateDay = $from->copy()->addDays($offset);
            $row = $rows->get((int) $candidateDay->isoWeekday());

            if (! $row || ! $row->opens_at) {
                continue;
            }

            $candidateOpen = $candidateDay->setTimeFromTimeString($row->opens_at);

            if ($candidateOpen->greaterThan($from)) {
                return $candidateOpen;
            }
        }

        return null;
    }
}
