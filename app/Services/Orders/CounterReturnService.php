<?php

namespace App\Services\Orders;

use App\Exceptions\RefundException;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Refund;
use App\Models\RefundItem;
use App\Models\User;
use App\Services\Settings\SettingsService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Returns at a pharmacy's or shop's till (owner-approved 2026-11-27; plan
 * step R3). The sale is found by its receipt number; the cashier picks what
 * came back; a manager approves with their Staff ID and PIN
 * (PinService::approver()); the money goes back the way the sale was paid,
 * at once (RefundService::directRefund(): a payments row and an
 * order_events row, as every refund).
 *
 * - Only within the owner's return window (setting `returns_days`, default
 *   7; 0 = no returns), and only what hasn't already come back.
 * - Each item's money is its share of what was paid: the line's price, less
 *   its share of any discount, plus its share of tax added on top. Never
 *   the delivery fee.
 * - The refund is kind `return`, lists its items (refund_items) and names
 *   the till it was given from (`shift_id`), so it comes off that till's
 *   takings (OrderReportService), whichever till made the sale.
 * - An exchange is a return then a new sale; the POS shows the difference.
 */
class CounterReturnService
{
    public const DEFAULT_DAYS = 7;

    public function __construct(
        private readonly RefundService $refunds,
        private readonly SettingsService $settings,
    ) {}

    public function returnDays(): int
    {
        return max(0, (int) $this->settings->get(SettingsService::RETURNS_DAYS, (string) self::DEFAULT_DAYS));
    }

    /**
     * The sale with the receipt number at this branch, and how many of each
     * line can still come back.
     *
     * @return array{order: Order, lines: list<array{id: int, name: string, quantity: int, returnable: int, unit_refund: int}>}
     */
    public function lookup(string $reference, int $branchId): array
    {
        $order = Order::where('branch_id', $branchId)->where('reference', strtoupper(trim($reference)))->with('items')->first();

        if (! $order) {
            throw ValidationException::withMessages(['reference' => __('No sale at this branch has the receipt number :reference. Check the number on the receipt.', ['reference' => strtoupper(trim($reference))])]);
        }
        $this->assertReturnable($order);

        $returned = $this->returnedQuantities($order);

        return [
            'order' => $order,
            'lines' => $order->items->where('is_free', false)->map(fn (OrderItem $item) => [
                'id' => $item->id,
                'name' => $item->name_snapshot,
                'quantity' => $item->quantity,
                'returnable' => max(0, $item->quantity - ($returned[$item->id] ?? 0)),
                'unit_refund' => $this->unitRefund($order, $item),
            ])->values()->all(),
        ];
    }

    /**
     * @param  array<int, int>  $quantities  order_item_id => how many came back
     */
    public function create(Order $order, array $quantities, User $approver, string $reason, ?int $shiftId): Refund
    {
        $this->assertReturnable($order);

        return DB::transaction(function () use ($order, $quantities, $approver, $reason, $shiftId) {
            // Locked, so two returns of the same sale can't both take the last one.
            Order::whereKey($order->id)->lockForUpdate()->first();
            $returned = $this->returnedQuantities($order);
            $items = $order->items()->where('is_free', false)->get()->keyBy('id');

            $lines = [];
            foreach (array_filter($quantities) as $itemId => $quantity) {
                $item = $items->get($itemId);
                $left = $item ? $item->quantity - ($returned[$item->id] ?? 0) : 0;
                if (! $item || $quantity < 1 || $quantity > $left) {
                    throw ValidationException::withMessages(['lines' => __('Only :left of :name can still be returned. Lower the number.', ['left' => max(0, $left), 'name' => $item?->name_snapshot ?? __('that item')])]);
                }
                $lines[] = ['item' => $item, 'quantity' => (int) $quantity, 'amount' => $this->unitRefund($order, $item) * (int) $quantity];
            }

            if ($lines === []) {
                throw ValidationException::withMessages(['lines' => __('Choose what\'s coming back: set how many of at least one item.')]);
            }

            $amount = array_sum(array_column($lines, 'amount'));

            try {
                $refund = $this->refunds->directRefund($order, $approver, $amount, $reason, 'staff', $shiftId);
            } catch (RefundException $e) {
                throw ValidationException::withMessages(['lines' => $e->getMessage()]);
            }

            $refund->update(['kind' => Refund::KIND_RETURN, 'shift_id' => $shiftId]);
            foreach ($lines as $line) {
                RefundItem::create(['refund_id' => $refund->id, 'order_item_id' => $line['item']->id, 'quantity' => $line['quantity'], 'amount' => $line['amount']]);
            }

            return $refund->fresh('items');
        });
    }

    private function assertReturnable(Order $order): void
    {
        $days = $this->returnDays();

        if ($days === 0) {
            throw ValidationException::withMessages(['reference' => __('This business doesn\'t take returns. The owner can change that under Settings.')]);
        }
        if ($order->payment_status !== 'paid' || in_array($order->status, ['cancelled', 'rejected', 'abandoned', 'pending_payment'], true)) {
            throw ValidationException::withMessages(['reference' => __('Sale :reference wasn\'t paid, so there\'s nothing to return.', ['reference' => $order->reference])]);
        }
        if ($order->placed_at && $order->placed_at->lt(now()->subDays($days)->startOfDay())) {
            throw ValidationException::withMessages(['reference' => trans_choice('Sale :reference is older than :days day, so it can\'t be returned here. A manager can still refund it from Refunds.|Sale :reference is older than :days days, so it can\'t be returned here. A manager can still refund it from Refunds.', $days, ['reference' => $order->reference, 'days' => $days])]);
        }
    }

    /** @return array<int, int> order_item_id => already returned */
    private function returnedQuantities(Order $order): array
    {
        return RefundItem::query()
            ->whereHas('refund', fn ($q) => $q->where('order_id', $order->id)->where('status', Refund::STATUS_COMPLETED))
            ->selectRaw('order_item_id, SUM(quantity) as qty')
            ->groupBy('order_item_id')
            ->pluck('qty', 'order_item_id')
            ->map(fn ($qty) => (int) $qty)
            ->all();
    }

    /** One unit's share of what was paid, never the delivery fee (pesewas). */
    private function unitRefund(Order $order, OrderItem $item): int
    {
        if ($order->subtotal <= 0 || $item->quantity <= 0) {
            return 0;
        }
        $goodsPaid = $order->total - $order->delivery_fee;

        return (int) floor($item->line_total / $item->quantity * $goodsPaid / $order->subtotal);
    }
}
