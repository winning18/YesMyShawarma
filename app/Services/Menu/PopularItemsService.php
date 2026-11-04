<?php

namespace App\Services\Menu;

use App\Models\Order;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * "Most ordered" for customers — the homepage strip, the "Popular" badge
 * on menu cards and the rank on an item's own page. Popularity, not a
 * rating (owner's choice, 2026-11-04, over per-item stars): units sold
 * across every branch over the last DAYS days, so it always has data and
 * can't be skewed by one review.
 *
 * Counts every real order, Bolt Food included — they're still people
 * choosing that item — and skips non-revenue statuses (cancelled,
 * rejected, never paid). Cached for an hour: it's on every customer page
 * and the order only shifts slowly.
 */
class PopularItemsService
{
    public const DAYS = 30;

    /** Items listed in the homepage strip. */
    public const TOP = 8;

    /** Items that get the "Popular" badge. */
    public const BADGE_TOP = 5;

    /** Fewer sold than this and an item isn't ranked at all — a quiet week shouldn't crown anything. */
    public const MIN_SOLD = 5;

    private const CACHE_KEY = 'menu:popular-items';

    /**
     * Rank by menu item id, 1 = most ordered.
     *
     * @return array<int, int>
     */
    public function ranks(): array
    {
        return Cache::remember(self::CACHE_KEY, now()->addHour(), function () {
            return DB::table('order_items')
                ->join('orders', 'orders.id', '=', 'order_items.order_id')
                ->join('menu_items', 'menu_items.id', '=', 'order_items.menu_item_id')
                ->where('orders.placed_at', '>=', now()->subDays(self::DAYS))
                ->whereNotIn('orders.status', Order::NON_REVENUE_STATUSES)
                ->where('menu_items.is_active', true)
                ->whereNull('menu_items.deleted_at')
                ->groupBy('order_items.menu_item_id')
                ->havingRaw('SUM(order_items.quantity) >= ?', [self::MIN_SOLD])
                ->orderByRaw('SUM(order_items.quantity) DESC')
                ->orderBy('order_items.menu_item_id')
                ->limit(self::TOP)
                ->pluck('order_items.menu_item_id')
                ->values()
                ->mapWithKeys(fn ($id, $index) => [(int) $id => $index + 1])
                ->all();
        });
    }

    public function rank(int $menuItemId): ?int
    {
        return $this->ranks()[$menuItemId] ?? null;
    }

    /**
     * Menu item ids that get the "Popular" badge.
     *
     * @return list<int>
     */
    public function badged(): array
    {
        return array_keys(array_filter($this->ranks(), fn (int $rank) => $rank <= self::BADGE_TOP));
    }
}
