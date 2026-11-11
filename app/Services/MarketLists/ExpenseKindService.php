<?php

namespace App\Services\MarketLists;

use App\Models\ExpenseCategory;
use App\Models\MarketList;
use App\Models\MarketListEvent;
use App\Models\MarketListItem;
use App\Models\Scopes\BranchScope;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Market list → Manage kinds (owner, 2026-11-11: owner, GM and manager).
 * Add, rename, reorder, hide/show kinds and edit the keywords that drive
 * ExpenseKindMatcher's suggestions. Others is the fallback and can't be
 * renamed or hidden. A hidden kind is no longer offered or suggested, but
 * past items keep it, so old reports don't change.
 *
 * After a kind gains keywords, items still sitting in Others that now
 * match can be moved across in one go — approved lists included, since
 * only the grouping changes, never an amount. Every list touched gets a
 * market_list_events row saying who moved what.
 */
class ExpenseKindService
{
    public function __construct(private readonly ExpenseKindMatcher $matcher) {}

    /**
     * @param  list<string>  $keywords
     */
    public function create(string $name, array $keywords): ExpenseCategory
    {
        return DB::transaction(function () use ($name, $keywords) {
            $others = $this->others();
            $sortOrder = $others->sort_order;

            // New kinds go just above Others, which always stays last.
            $others->update(['sort_order' => $sortOrder + 1]);

            return ExpenseCategory::create([
                'name' => trim($name),
                'keywords' => $keywords,
                'sort_order' => $sortOrder,
                'is_active' => true,
            ]);
        });
    }

    /**
     * @param  list<string>  $keywords
     */
    public function update(ExpenseCategory $kind, string $name, array $keywords): ExpenseCategory
    {
        $kind->update($this->isOthers($kind)
            ? ['keywords' => []]
            : ['name' => trim($name), 'keywords' => $keywords]);

        return $kind;
    }

    public function setActive(ExpenseCategory $kind, bool $active): void
    {
        if (! $this->isOthers($kind)) {
            $kind->update(['is_active' => $active]);
        }
    }

    /**
     * Swap with the neighbour above or below. Others never moves.
     */
    public function move(ExpenseCategory $kind, string $direction): void
    {
        if ($this->isOthers($kind)) {
            return;
        }

        $neighbour = ExpenseCategory::where('name', '!=', ExpenseCategory::OTHERS)
            ->where('sort_order', $direction === 'up' ? '<' : '>', $kind->sort_order)
            ->orderBy('sort_order', $direction === 'up' ? 'desc' : 'asc')
            ->first();

        if ($neighbour) {
            DB::transaction(function () use ($kind, $neighbour) {
                [$a, $b] = [$kind->sort_order, $neighbour->sort_order];
                $kind->update(['sort_order' => $b]);
                $neighbour->update(['sort_order' => $a]);
            });
        }
    }

    /**
     * Items in Others that the suggestion rule would now put in $kind.
     *
     * @return Collection<int, MarketListItem>
     */
    public function othersMatching(ExpenseCategory $kind): Collection
    {
        if ($this->isOthers($kind) || ! $kind->is_active || empty($kind->keywords)) {
            return collect();
        }

        $matcher = new ExpenseKindMatcher;

        return MarketListItem::where('expense_category_id', $this->others()->id)
            ->with(['marketList' => fn ($query) => $query->withoutGlobalScope(BranchScope::class)->with('branch:id,name')])
            ->orderByDesc('id')
            ->limit(500)
            ->get()
            ->filter(fn (MarketListItem $item) => $matcher->match($item->description)->is($kind))
            ->values();
    }

    /**
     * @param  list<int>  $itemIds
     * @return int how many moved
     */
    public function moveFromOthers(ExpenseCategory $kind, array $itemIds, User $user): int
    {
        $allowed = $this->othersMatching($kind)->whereIn('id', $itemIds);

        DB::transaction(function () use ($kind, $allowed, $user) {
            MarketListItem::whereIn('id', $allowed->pluck('id'))->update(['expense_category_id' => $kind->id]);

            foreach ($allowed->groupBy('market_list_id') as $listId => $items) {
                MarketList::withoutGlobalScope(BranchScope::class)->find($listId)?->events()->create([
                    'actor_id' => $user->id,
                    'action' => MarketListEvent::KIND_CHANGED,
                    'note' => trans_choice(':count item moved from Others to :kind (:items).|:count items moved from Others to :kind (:items).', $items->count(), [
                        'count' => $items->count(),
                        'kind' => $kind->name,
                        'items' => $items->pluck('description')->join(', '),
                    ]),
                ]);
            }
        });

        return $allowed->count();
    }

    /**
     * "Cheese, mozzarella ,CHEDDAR,," → ['cheese', 'mozzarella', 'cheddar'].
     *
     * @return list<string>
     */
    public function parseKeywords(?string $raw): array
    {
        return collect(explode(',', (string) $raw))
            ->map(fn (string $word) => mb_strtolower(trim(preg_replace('/\s+/', ' ', $word))))
            ->filter(fn (string $word) => $word !== '' && mb_strlen($word) <= 40)
            ->unique()
            ->values()
            ->all();
    }

    public function isOthers(ExpenseCategory $kind): bool
    {
        return $kind->name === ExpenseCategory::OTHERS;
    }

    private function others(): ExpenseCategory
    {
        return $this->matcher->others();
    }
}
