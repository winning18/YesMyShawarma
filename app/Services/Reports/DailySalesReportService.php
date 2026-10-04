<?php

namespace App\Services\Reports;

use App\Models\MenuItemComponent;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Backs the staff-facing "Today" report — single-channel (web or pos),
 * item-level sales by category plus a Modifiers section, and the
 * payment-method breakdown, for whatever window of time is given it.
 * Despite the name, $dayStart/$dayEnd were never actually required to be
 * a whole calendar day — this takes any precise Carbon range, which is
 * what lets TodayReportController offer a date picker and a "view this
 * shift's exact window" link (a shift that runs past midnight has no
 * single calendar day that correctly represents it, but it always has an
 * exact started_at/ended_at). Branch-scoped like the rest of the Reports
 * section (plain Order::query() calls, BranchScope applies).
 *
 * total_sales/by_payment_method come from OrderReportService::
 * financialSummary() (net of refunds) rather than being summed here
 * independently — this page and Performance used to compute "total sales"
 * two different ways (this one gross, Performance's net-of-refunds), which
 * meant the same real-world sales could show two different numbers
 * depending which screen you were on. One formula now, shared.
 *
 * The category/modifier split is driven entirely by MenuItemComponent —
 * a combo item (e.g. "Signature (Chicken, Cheese & Sausage)") with
 * configured components is never itself recorded as a line; instead each
 * component line is recorded under its own category (base items) or
 * Modifiers (modifier components), scaled by the order item's quantity.
 * A plain item with no configured components is recorded under its own
 * name as usual. Real customer-selected options (order_item_options) are
 * always recorded under Modifiers too, additively — a combo's implied
 * Cheese and a customer's own added Cheese both count.
 */
class DailySalesReportService
{
    public function __construct(private readonly OrderReportService $reports) {}

    /**
     * @return array{
     *   categories: Collection<int, array{category: string, items: Collection<int, array{name: string, qty: int, unit: int, total: int}>, subtotal: int}>,
     *   modifiers: array{items: Collection<int, array{name: string, qty: int, unit: int, total: int}>, subtotal: int},
     *   total_sales: int,
     *   orders_count: int,
     *   by_payment_method: Collection<string, int>,
     * }
     */
    public function summary(Carbon $dayStart, Carbon $dayEnd, string $channel, ?string $paymentMethod = null): array
    {
        $orders = Order::with([
            'items.menuItem' => fn ($query) => $query->withTrashed(),
            'items.menuItem.category',
            'items.menuItem.components.componentMenuItem.category',
            'items.menuItem.components.componentOption',
            'items.options',
        ])
            ->whereBetween('placed_at', [$dayStart->clone()->utc(), $dayEnd->clone()->utc()])
            ->where('channel', $channel)
            ->whereNotIn('status', Order::NON_REVENUE_STATUSES)
            // $paymentMethod set (e.g. TodayReportController's "Bolt Food"
            // view) means the caller explicitly wants exactly that method
            // — including Bolt Food, which is otherwise always excluded
            // here: Bolt Food orders are always channel 'pos' but never
            // "our" sales in the general case (Bolt collects that money,
            // not this till — orders.md), so the default view must keep
            // excluding it to stay an accurate picture of this till's own
            // takings.
            ->when(
                $paymentMethod,
                fn ($query) => $query->where('payment_method', $paymentMethod),
                fn ($query) => $query->whereNotIn('payment_method', Order::EXCLUDED_FROM_SALES_PAYMENT_METHODS),
            )
            ->get();

        /** @var array<string, array<string, array{name: string, qty: int, unit: int, total: int}>> $categoryBuckets */
        $categoryBuckets = [];
        /** @var array<string, array{name: string, qty: int, unit: int, total: int}> $modifierBucket */
        $modifierBucket = [];

        foreach ($orders as $order) {
            foreach ($order->items as $orderItem) {
                $this->recordOrderItem($orderItem, $categoryBuckets, $modifierBucket);
            }
        }

        $categories = collect($categoryBuckets)
            ->map(fn (array $lines, string $categoryName) => [
                'category' => $categoryName,
                'items' => collect($lines)->sortByDesc('total')->values(),
                'subtotal' => (int) collect($lines)->sum('total'),
            ])
            ->sortBy('category')
            ->values();

        $modifierLines = collect($modifierBucket)->sortByDesc('total')->values();

        $financial = $this->reports->financialSummary($dayStart->clone()->utc(), $dayEnd->clone()->utc(), channel: $channel, paymentMethod: $paymentMethod);

        return [
            'categories' => $categories,
            'modifiers' => ['items' => $modifierLines, 'subtotal' => (int) $modifierLines->sum('total')],
            'total_sales' => $financial['revenue_total'],
            'orders_count' => $orders->count(),
            'by_payment_method' => $financial['revenue_by_payment_method'],
        ];
    }

    /**
     * @param  array<string, array<string, array{name: string, qty: int, unit: int, total: int}>>  $categoryBuckets
     * @param  array<string, array{name: string, qty: int, unit: int, total: int}>  $modifierBucket
     */
    private function recordOrderItem(OrderItem $orderItem, array &$categoryBuckets, array &$modifierBucket): void
    {
        $menuItem = $orderItem->menuItem;
        $components = $menuItem?->components ?? collect();

        if ($components->isEmpty()) {
            $categoryName = $menuItem?->category?->name ?? __('Uncategorised');
            $key = 'item:'.($menuItem?->id ?? $orderItem->name_snapshot);
            $categoryBuckets[$categoryName] ??= [];
            $this->accumulate($categoryBuckets[$categoryName], $key, $orderItem->name_snapshot, $orderItem->quantity, $orderItem->unit_price_snapshot);
        } else {
            foreach ($components as $component) {
                $qty = $component->quantity * $orderItem->quantity;

                if ($component->component_type === MenuItemComponent::TYPE_BASE) {
                    $base = $component->componentMenuItem;

                    if (! $base) {
                        continue;
                    }

                    $categoryName = $base->category?->name ?? __('Uncategorised');
                    $key = 'item:'.$base->id;
                    $categoryBuckets[$categoryName] ??= [];
                    $this->accumulate($categoryBuckets[$categoryName], $key, $base->name, $qty, $base->base_price);
                } else {
                    $option = $component->componentOption;

                    if (! $option) {
                        continue;
                    }

                    $this->accumulate($modifierBucket, 'option:'.$option->id, $option->name, $qty, $option->price_delta);
                }
            }
        }

        // Real customer-selected options — additive on top of whatever the
        // combo itself implies (see class docblock's second worked example).
        foreach ($orderItem->options as $optionRow) {
            $key = $optionRow->option_id ? 'option:'.$optionRow->option_id : 'option-name:'.$optionRow->name_snapshot;
            $this->accumulate($modifierBucket, $key, $optionRow->name_snapshot, $orderItem->quantity, $optionRow->price_delta_snapshot);
        }
    }

    /**
     * @param  array<string, array{name: string, qty: int, unit: int, total: int}>  $bucket
     */
    private function accumulate(array &$bucket, string $key, string $name, int $qty, int $unitPrice): void
    {
        $bucket[$key] ??= ['name' => $name, 'qty' => 0, 'unit' => $unitPrice, 'total' => 0];
        $bucket[$key]['qty'] += $qty;
        $bucket[$key]['total'] += $qty * $unitPrice;
    }
}
