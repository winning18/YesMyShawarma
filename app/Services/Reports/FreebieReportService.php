<?php

namespace App\Services\Reports;

use App\Models\ComplimentaryOrder;
use App\Models\Order;
use App\Models\Scopes\BranchScope;
use App\Services\Tax\TaxService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * The Freebies & rewards page (owner, 2026-11-14): every freebie in a
 * period with its menu value (the order's subtotal), who got it, why, who
 * gave it and its approval, plus totals by reason, giver and recipient;
 * and how many WhatsApp group orders there were and what their GH₵10
 * rewards came to. Branches are passed in explicitly by the caller.
 */
class FreebieReportService
{
    /**
     * @param  list<int>|null  $branchIds  null = every branch
     * @return Collection<int, ComplimentaryOrder>
     */
    public function freebies(Carbon $from, Carbon $to, ?array $branchIds, ?string $status = null): Collection
    {
        return ComplimentaryOrder::query()
            ->whereHas('order', fn (Builder $order) => $this->scopeOrders($order, $from, $to, $branchIds))
            ->when($status, fn (Builder $query) => $query->where('status', $status))
            ->with(['order.items', 'order.branch:id,name', 'recipientUser:id,name', 'recordedBy:id,name', 'reviewedBy:id,name'])
            ->get()
            ->sortByDesc(fn (ComplimentaryOrder $freebie) => $freebie->order->placed_at)
            ->values();
    }

    /**
     * @param  Collection<int, ComplimentaryOrder>  $freebies
     * @return array<string, mixed>
     */
    public function summary(Collection $freebies): array
    {
        $value = fn (Collection $rows) => (int) $rows->sum(fn (ComplimentaryOrder $freebie) => $freebie->order->subtotal);
        $byStatus = $freebies->groupBy('status');
        $group = fn (callable $key) => $freebies
            ->groupBy($key)
            ->map(fn (Collection $rows, string $label) => ['label' => $label, 'count' => $rows->count(), 'value' => $value($rows)])
            ->sortByDesc('value')
            ->values()
            ->all();

        return [
            'count' => $freebies->count(),
            'value' => $value($freebies),
            'approved' => ['count' => $byStatus->get('approved', collect())->count(), 'value' => $value($byStatus->get('approved', collect()))],
            'pending' => ['count' => $byStatus->get('pending', collect())->count(), 'value' => $value($byStatus->get('pending', collect()))],
            'rejected' => ['count' => $byStatus->get('rejected', collect())->count(), 'value' => $value($byStatus->get('rejected', collect()))],
            'by_reason' => $group(fn (ComplimentaryOrder $freebie) => ucfirst(mb_strtolower(trim($freebie->reason)))),
            'by_giver' => $group(fn (ComplimentaryOrder $freebie) => $freebie->recordedBy?->name ?? __('Unknown')),
            'by_recipient' => $group(fn (ComplimentaryOrder $freebie) => $freebie->recipientLabel()),
        ];
    }

    /**
     * WhatsApp group orders that counted as sales, and the rewards given.
     *
     * @param  list<int>|null  $branchIds
     * @return array{count: int, rewards: int, sales: int}
     */
    public function whatsapp(Carbon $from, Carbon $to, ?array $branchIds): array
    {
        $orders = Order::withoutGlobalScope(BranchScope::class)
            ->where('source', Order::SOURCE_WHATSAPP)
            ->whereNotIn('status', Order::NON_REVENUE_STATUSES)
            ->whereNotNull('placed_at')
            ->whereBetween('placed_at', [$from->clone()->utc(), $to->clone()->utc()])
            ->when($branchIds !== null, fn (Builder $query) => $query->whereIn('branch_id', $branchIds))
            ->get(['id', 'subtotal', 'discount_total', 'tax_total', 'promotion_id']);
        $tax = app(TaxService::class)->breakdown($orders);

        return [
            'count' => $orders->count(),
            'rewards' => (int) $orders->whereNull('promotion_id')->sum('discount_total'),
            'sales' => (int) $orders->sum(fn (Order $order) => app(TaxService::class)->netFood($order, $tax)),
        ];
    }

    /**
     * @param  list<int>|null  $branchIds
     */
    private function scopeOrders(Builder $order, Carbon $from, Carbon $to, ?array $branchIds): Builder
    {
        return $order->withoutGlobalScope(BranchScope::class)
            ->whereNotNull('placed_at')
            ->whereBetween('placed_at', [$from->clone()->utc(), $to->clone()->utc()])
            ->when($branchIds !== null, fn (Builder $query) => $query->whereIn('branch_id', $branchIds));
    }
}
