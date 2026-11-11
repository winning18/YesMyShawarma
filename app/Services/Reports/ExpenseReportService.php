<?php

namespace App\Services\Reports;

use App\Models\ExpenseCategory;
use App\Models\MarketList;
use App\Models\MarketListItem;
use App\Models\Scopes\BranchScope;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * The Expenses tab: every market-list item (purchases and expenses,
 * including what's entered at shift close — see MarketListService) for a
 * date range, by purchase date, filterable by branch, kind, status, who
 * recorded it, description text and amount, and downloadable in the
 * shared report design (ExpenseReportExporter).
 *
 * Only approved items count by default; status can be widened to see
 * what's still waiting.
 *
 * Branch arguments follow WeeklySalesReportService's convention, resolved
 * by ReportBranchResolver.
 *
 * $filters keys (all optional): search (string), recorded_by (user id),
 * kind (expense_category id), status (a MarketList status or 'all',
 * default approved), min_amount / max_amount (pesewas).
 */
class ExpenseReportService
{
    public const STATUS_ALL = 'all';

    public function __construct(private readonly ReportBranchResolver $branches) {}

    /**
     * @param  array<string, mixed>  $filters
     * @param  ?list<int>  $branchIds
     */
    public function paginate(Carbon $from, Carbon $to, array $filters, int $perPage = 25, bool $ignoreBranchScope = false, ?int $branchId = null, ?array $branchIds = null): LengthAwarePaginator
    {
        $scopeIds = $this->branches->branchIds($ignoreBranchScope, $branchId, $branchIds);

        return $this->query($from, $to, $filters, $scopeIds)
            ->orderByDesc(MarketList::select('purchased_on')->withoutGlobalScope(BranchScope::class)->whereColumn('market_lists.id', 'market_list_items.market_list_id'))
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (MarketListItem $item) => $this->row($item));
    }

    /**
     * Totals for the filtered set as a whole, not just the current page.
     *
     * @param  array<string, mixed>  $filters
     * @param  ?list<int>  $branchIds
     * @return array{total: int, count: int, lists_count: int}
     */
    public function summary(Carbon $from, Carbon $to, array $filters, bool $ignoreBranchScope = false, ?int $branchId = null, ?array $branchIds = null): array
    {
        $scopeIds = $this->branches->branchIds($ignoreBranchScope, $branchId, $branchIds);
        $query = $this->query($from, $to, $filters, $scopeIds);

        return [
            'total' => (int) (clone $query)->sum('amount'),
            'count' => (clone $query)->count(),
            'lists_count' => (clone $query)->distinct()->count('market_list_id'),
        ];
    }

    /**
     * The downloadable report: oldest first, grouped by day with a total
     * per day, same shape as SalesStatementService's days, plus a total
     * per kind.
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
            ->get()
            ->map(fn (MarketListItem $item) => $this->row($item))
            ->sortBy(fn (array $row) => [$row['date'], $row['id']])
            ->values();

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
            'by_kind' => $rows->groupBy('kind')
                ->map(fn (Collection $kindRows, string $kind) => ['kind' => $kind, 'count' => $kindRows->count(), 'total' => (int) $kindRows->sum('amount')])
                ->sortByDesc('total')
                ->values()
                ->all(),
            'total' => (int) $rows->sum('amount'),
            'count' => $rows->count(),
            'lists_count' => $rows->pluck('list_id')->unique()->count(),
            'filters' => $this->describeFilters($filters),
        ];
    }

    /**
     * Everyone who has recorded a market list at the branches in scope,
     * for the "Recorded by" filter.
     *
     * @param  ?list<int>  $branchIds
     * @return Collection<int, User>
     */
    public function recordedByOptions(bool $ignoreBranchScope = false, ?int $branchId = null, ?array $branchIds = null): Collection
    {
        $scopeIds = $this->branches->branchIds($ignoreBranchScope, $branchId, $branchIds);

        $userIds = MarketList::withoutGlobalScope(BranchScope::class)
            ->when($scopeIds !== null, fn (Builder $query) => $query->whereIn('branch_id', $scopeIds))
            ->distinct()
            ->pluck('recorded_by');

        return User::whereIn('id', $userIds)->orderBy('name')->get(['id', 'name']);
    }

    /**
     * @return Collection<int, ExpenseCategory>
     */
    public function kindOptions(): Collection
    {
        return ExpenseCategory::orderBy('sort_order')->get(['id', 'name']);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @param  ?list<int>  $scopeIds
     */
    private function query(Carbon $from, Carbon $to, array $filters, ?array $scopeIds): Builder
    {
        $search = trim((string) ($filters['search'] ?? ''));
        $status = $filters['status'] ?? MarketList::STATUS_APPROVED;

        return MarketListItem::query()
            ->with(['marketList' => fn ($list) => $list->withoutGlobalScope(BranchScope::class)->with(['branch:id,name', 'recordedBy:id,name']), 'category:id,name'])
            ->whereHas('marketList', fn (Builder $list) => $list
                ->withoutGlobalScope(BranchScope::class)
                ->whereBetween('purchased_on', [$from->toDateString(), $to->toDateString()])
                ->when($status !== self::STATUS_ALL, fn (Builder $query) => $query->where('status', $status))
                ->when($scopeIds !== null, fn (Builder $query) => $query->whereIn('branch_id', $scopeIds))
                ->when(isset($filters['recorded_by']), fn (Builder $query) => $query->where('recorded_by', $filters['recorded_by'])))
            ->when(isset($filters['kind']), fn (Builder $query) => $query->where('expense_category_id', $filters['kind']))
            ->when($search !== '', fn (Builder $query) => $query->where('description', 'like', '%'.addcslashes($search, '%_\\').'%'))
            ->when(isset($filters['min_amount']), fn (Builder $query) => $query->where('amount', '>=', $filters['min_amount']))
            ->when(isset($filters['max_amount']), fn (Builder $query) => $query->where('amount', '<=', $filters['max_amount']));
    }

    /**
     * @return array{id: int, list_id: int, date: string, kind: string, branch: string, recorded_by: string, description: string, status: string, amount: int}
     */
    private function row(MarketListItem $item): array
    {
        $list = $item->marketList;

        return [
            'id' => $item->id,
            'list_id' => $item->market_list_id,
            'date' => $list->purchased_on->toDateString(),
            'kind' => $item->category?->name ?? '',
            'branch' => $list->branch?->name ?? '',
            'recorded_by' => $list->recordedBy?->name ?? '',
            'description' => $item->description,
            'status' => Str::headline($list->status),
            'amount' => (int) $item->amount,
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
        $status = $filters['status'] ?? MarketList::STATUS_APPROVED;

        if ($status !== MarketList::STATUS_APPROVED) {
            $described[] = $status === self::STATUS_ALL ? 'Every status, including not yet approved' : 'Status: '.Str::headline($status).' (not counted in profit)';
        }
        if (isset($filters['kind'])) {
            $described[] = 'Kind: '.(ExpenseCategory::find($filters['kind'])?->name ?? 'unknown');
        }
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
