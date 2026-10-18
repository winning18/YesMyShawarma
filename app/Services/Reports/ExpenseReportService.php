<?php

namespace App\Services\Reports;

use App\Models\Shift;
use App\Models\ShiftExpense;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * The Expenses tab: every shift expense (entered by whoever closes a
 * shift — see ShiftService::end()) for a date range, filterable by
 * branch, who recorded it, description text and amount, and downloadable
 * in the shared report design (ExpenseReportExporter).
 *
 * "Recorded by" is the shift's ended_by user, falling back to the user
 * who opened it — expenses are only ever entered at shift close, so
 * that's the person who typed them.
 *
 * Branch arguments follow WeeklySalesReportService's convention, resolved
 * by ReportBranchResolver.
 *
 * $filters keys (all optional): search (string), recorded_by (user id),
 * min_amount / max_amount (pesewas).
 */
class ExpenseReportService
{
    public function __construct(private readonly ReportBranchResolver $branches) {}

    /**
     * @param  array<string, mixed>  $filters
     * @param  ?list<int>  $branchIds
     */
    public function paginate(Carbon $from, Carbon $to, array $filters, int $perPage = 25, bool $ignoreBranchScope = false, ?int $branchId = null, ?array $branchIds = null): LengthAwarePaginator
    {
        $scopeIds = $this->branches->branchIds($ignoreBranchScope, $branchId, $branchIds);

        return $this->query($from, $to, $filters, $scopeIds)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (ShiftExpense $expense) => $this->row($expense));
    }

    /**
     * Totals for the filtered set as a whole, not just the current page.
     *
     * @param  array<string, mixed>  $filters
     * @param  ?list<int>  $branchIds
     * @return array{total: int, count: int, shifts_count: int}
     */
    public function summary(Carbon $from, Carbon $to, array $filters, bool $ignoreBranchScope = false, ?int $branchId = null, ?array $branchIds = null): array
    {
        $scopeIds = $this->branches->branchIds($ignoreBranchScope, $branchId, $branchIds);
        $query = $this->query($from, $to, $filters, $scopeIds);

        return [
            'total' => (int) (clone $query)->sum('amount'),
            'count' => (clone $query)->count(),
            'shifts_count' => (clone $query)->distinct()->count('shift_id'),
        ];
    }

    /**
     * The downloadable report: oldest first, grouped by day with a total
     * per day, same shape as SalesStatementService's days.
     *
     * @param  array<string, mixed>  $filters
     * @param  ?list<int>  $branchIds
     * @return array<string, mixed>
     */
    public function build(Carbon $from, Carbon $to, array $filters, bool $ignoreBranchScope = false, ?int $branchId = null, ?array $branchIds = null): array
    {
        $scopeIds = $this->branches->branchIds($ignoreBranchScope, $branchId, $branchIds);
        $singleBranchId = $this->branches->singleBranchId($ignoreBranchScope, $branchId, $branchIds);

        $rows = $this->query($from, $to, $filters, $scopeIds)
            ->orderBy('created_at')
            ->orderBy('id')
            ->get()
            ->map(fn (ShiftExpense $expense) => $this->row($expense));

        $days = $rows
            ->groupBy('date')
            ->map(fn (Collection $dayRows, string $date) => [
                'date' => $date,
                'rows' => $dayRows->values()->all(),
                'total' => (int) $dayRows->sum('amount'),
            ])
            ->values()
            ->all();

        return [
            'title' => 'Expenses report',
            'from' => $from,
            'to' => $to,
            'branch' => $this->branches->headerBranch($singleBranchId),
            'general' => $singleBranchId === null,
            'days' => $days,
            'total' => (int) $rows->sum('amount'),
            'count' => $rows->count(),
            'shifts_count' => $rows->pluck('shift_id')->unique()->count(),
            'filters' => $this->describeFilters($filters),
        ];
    }

    /**
     * Everyone who has recorded an expense at the branches in scope, for
     * the "Recorded by" filter.
     *
     * @param  ?list<int>  $branchIds
     * @return Collection<int, User>
     */
    public function recordedByOptions(bool $ignoreBranchScope = false, ?int $branchId = null, ?array $branchIds = null): Collection
    {
        $scopeIds = $this->branches->branchIds($ignoreBranchScope, $branchId, $branchIds);

        $userIds = Shift::query()
            ->whereHas('expenses')
            ->when($scopeIds !== null, fn (Builder $query) => $query->whereIn('branch_id', $scopeIds))
            ->get(['user_id', 'ended_by_user_id'])
            ->map(fn (Shift $shift) => $shift->ended_by_user_id ?? $shift->user_id)
            ->unique()
            ->values();

        return User::whereIn('id', $userIds)->orderBy('name')->get(['id', 'name']);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @param  ?list<int>  $scopeIds
     */
    private function query(Carbon $from, Carbon $to, array $filters, ?array $scopeIds): Builder
    {
        $search = trim((string) ($filters['search'] ?? ''));
        $recordedBy = $filters['recorded_by'] ?? null;

        return ShiftExpense::query()
            ->with(['shift.branch:id,name', 'shift.endedBy:id,name', 'shift.user:id,name'])
            ->whereBetween('created_at', [$from->clone()->utc(), $to->clone()->utc()])
            ->when($scopeIds !== null, fn (Builder $query) => $query->whereHas('shift', fn (Builder $shift) => $shift->whereIn('branch_id', $scopeIds)))
            ->when($recordedBy !== null, fn (Builder $query) => $query->whereHas('shift', fn (Builder $shift) => $shift
                ->where('ended_by_user_id', $recordedBy)
                ->orWhere(fn (Builder $unended) => $unended->whereNull('ended_by_user_id')->where('user_id', $recordedBy))))
            ->when($search !== '', fn (Builder $query) => $query->where('description', 'like', '%'.addcslashes($search, '%_\\').'%'))
            ->when(isset($filters['min_amount']), fn (Builder $query) => $query->where('amount', '>=', $filters['min_amount']))
            ->when(isset($filters['max_amount']), fn (Builder $query) => $query->where('amount', '<=', $filters['max_amount']));
    }

    /**
     * @return array{id: int, shift_id: int, date: string, time: string, branch: string, recorded_by: string, description: string, amount: int}
     */
    private function row(ShiftExpense $expense): array
    {
        $at = $expense->created_at->clone()->timezone('Africa/Accra');
        $shift = $expense->shift;

        return [
            'id' => $expense->id,
            'shift_id' => $expense->shift_id,
            'date' => $at->toDateString(),
            'time' => $at->format('H:i'),
            'branch' => $shift?->branch?->name ?? '',
            'recorded_by' => ($shift?->endedBy ?? $shift?->user)?->name ?? '',
            'description' => $expense->description,
            'amount' => (int) $expense->amount,
        ];
    }

    /**
     * Human-readable list of the active filters, printed on the report so
     * a filtered download can't be mistaken for the full picture.
     *
     * @param  array<string, mixed>  $filters
     * @return list<string>
     */
    private function describeFilters(array $filters): array
    {
        $described = [];

        if (trim((string) ($filters['search'] ?? '')) !== '') {
            $described[] = 'Description contains "'.trim($filters['search']).'"';
        }
        if (isset($filters['recorded_by'])) {
            $described[] = 'Recorded by '.(User::find($filters['recorded_by'])?->name ?? 'unknown');
        }
        if (isset($filters['min_amount'])) {
            $described[] = 'Amount from GHS '.number_format($filters['min_amount'] / 100, 2);
        }
        if (isset($filters['max_amount'])) {
            $described[] = 'Amount up to GHS '.number_format($filters['max_amount'] / 100, 2);
        }

        return $described;
    }
}
