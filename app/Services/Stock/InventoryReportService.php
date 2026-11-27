<?php

namespace App\Services\Stock;

use App\Models\Order;
use App\Models\Refund;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * A pharmacy's or shop's stock reports (owner-approved 2026-11-27; plan step
 * R4), for one branch. Amounts in pesewas.
 *
 * - Profit by product: what each sold for and what it cost when sold
 *   (order_items.cost_snapshot), less what came back (returns). A sale with
 *   no cost recorded counts its sales but no cost, and is flagged.
 * - Stock value: what's on the shelf at its latest cost.
 * - Not selling: products with stock and no sale for the chosen days.
 */
class InventoryReportService
{
    /**
     * @return Collection<int, object{menu_item_id: int, name: string, units: int, sales: int, cost: int, returned: int, profit: int, missing_cost: bool}>
     */
    public function profitByProduct(int $branchId, CarbonInterface $from, CarbonInterface $to): Collection
    {
        $sold = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->join('menu_items', 'menu_items.id', '=', 'order_items.menu_item_id')
            ->where('orders.branch_id', $branchId)
            ->where('orders.payment_status', 'paid')
            ->whereNotIn('orders.status', Order::NON_REVENUE_STATUSES)
            ->whereBetween('orders.placed_at', [$from, $to])
            ->groupBy('order_items.menu_item_id', 'menu_items.name')
            ->selectRaw('order_items.menu_item_id, menu_items.name, SUM(order_items.quantity * order_items.units) as units, SUM(order_items.line_total) as sales,
                SUM(COALESCE(order_items.cost_snapshot, 0) * order_items.quantity) as cost, MAX(order_items.cost_snapshot IS NULL AND order_items.is_free = 0) as missing_cost')
            ->get()->keyBy('menu_item_id');

        $returned = DB::table('refund_items')
            ->join('refunds', 'refunds.id', '=', 'refund_items.refund_id')
            ->join('order_items', 'order_items.id', '=', 'refund_items.order_item_id')
            ->where('refunds.branch_id', $branchId)
            ->where('refunds.status', Refund::STATUS_COMPLETED)
            ->whereBetween('refunds.completed_at', [$from, $to])
            ->groupBy('order_items.menu_item_id')
            ->selectRaw('order_items.menu_item_id, SUM(refund_items.amount) as amount,
                SUM(CASE WHEN refund_items.restocked THEN COALESCE(order_items.cost_snapshot, 0) * refund_items.quantity ELSE 0 END) as cost_back')
            ->get()->keyBy('menu_item_id');

        return $sold->map(function ($row) use ($returned) {
            $back = $returned[$row->menu_item_id] ?? null;
            $sales = (int) $row->sales;
            // Restocked returns give their cost back; damaged ones stay a cost.
            $cost = (int) $row->cost - (int) ($back->cost_back ?? 0);
            $returnedAmount = (int) ($back->amount ?? 0);

            return (object) [
                'menu_item_id' => (int) $row->menu_item_id,
                'name' => $row->name,
                'units' => (int) $row->units,
                'sales' => $sales,
                'cost' => $cost,
                'returned' => $returnedAmount,
                'profit' => $sales - $returnedAmount - $cost,
                'missing_cost' => (bool) $row->missing_cost,
            ];
        })->sortByDesc('profit')->values();
    }

    /** @return Collection<int, object{name: string, quantity: float, unit: string, unit_cost: ?int, value: int}> */
    public function stockValue(int $branchId): Collection
    {
        return DB::table('stock_items')
            ->join('menu_items', 'menu_items.id', '=', 'stock_items.menu_item_id')
            ->where('stock_items.branch_id', $branchId)
            ->whereNull('menu_items.deleted_at')
            ->where('stock_items.quantity', '>', 0)
            ->orderBy('menu_items.name')
            ->get(['menu_items.name', 'stock_items.quantity', 'stock_items.unit', 'menu_items.cost_price'])
            ->map(fn ($r) => (object) [
                'name' => $r->name,
                'quantity' => (float) $r->quantity,
                'unit' => $r->unit,
                'unit_cost' => $r->cost_price === null ? null : (int) $r->cost_price,
                'value' => (int) round((float) $r->quantity * (int) ($r->cost_price ?? 0)),
            ]);
    }

    /** @return Collection<int, object{name: string, quantity: float, unit: string, last_sold: ?string}> */
    public function notSelling(int $branchId, int $days): Collection
    {
        $lastSold = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.branch_id', $branchId)
            ->whereNotIn('orders.status', Order::NON_REVENUE_STATUSES)
            ->groupBy('order_items.menu_item_id')
            ->selectRaw('order_items.menu_item_id, MAX(orders.placed_at) as last_sold');

        return DB::table('stock_items')
            ->join('menu_items', 'menu_items.id', '=', 'stock_items.menu_item_id')
            ->leftJoinSub($lastSold, 'sold', 'sold.menu_item_id', '=', 'stock_items.menu_item_id')
            ->where('stock_items.branch_id', $branchId)
            ->whereNull('menu_items.deleted_at')
            ->where('stock_items.quantity', '>', 0)
            ->where(fn ($q) => $q->whereNull('sold.last_sold')->orWhere('sold.last_sold', '<', now()->subDays($days)))
            ->orderBy('sold.last_sold')
            ->get(['menu_items.name', 'stock_items.quantity', 'stock_items.unit', 'sold.last_sold'])
            ->map(fn ($r) => (object) ['name' => $r->name, 'quantity' => (float) $r->quantity, 'unit' => $r->unit, 'last_sold' => $r->last_sold]);
    }
}
