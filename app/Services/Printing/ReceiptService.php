<?php

namespace App\Services\Printing;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderItemOption;
use App\Models\TableTab;
use App\Models\User;
use App\Services\Settings\BusinessProfile;
use App\Services\Settings\BusinessType;
use App\Services\Tables\TableTabService;
use App\Services\Tax\TaxService;

/**
 * What a receipt and a kitchen ticket say (Express POS step 8a) — built
 * once here so the browser print pages (8a) and the print agent's ESC/POS
 * (8b) print exactly the same thing.
 */
class ReceiptService
{
    public function __construct(
        private readonly BusinessProfile $profile,
        private readonly TaxService $tax,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function receipt(Order $order): array
    {
        $order->loadMissing(['items.options', 'branch', 'customer', 'shift.terminal', 'events']);
        // Tax as it was at the sale (step 9): each line's food and delivery parts together.
        $taxes = $this->tax->linesFor($order);

        return [
            'business' => $this->profile->name(),
            'logo' => $this->profile->logoUrl(),
            'branch' => $order->branch?->name,
            'address' => $order->branch?->address,
            'phone' => $this->phoneDisplay((string) $order->branch?->phone),
            'reference' => $order->reference,
            'placed_at' => $order->placed_at?->timezone('Africa/Accra'),
            'served_by' => $this->servedBy($order),
            'counter' => $order->shift?->terminal?->name,
            'table' => $order->tableTab?->table?->name,
            'bill' => app(TableTabService::class)->roundOf($order),
            'fulfilment' => $order->fulfilment_type,
            'customer' => $order->customer?->name,
            'lines' => $this->lines($order),
            'subtotal' => (int) $order->subtotal,
            'discount' => (int) $order->discount_total,
            'delivery_fee' => (int) $order->delivery_fee,
            'total' => (int) $order->total,
            'taxes' => $taxes->map(fn ($tax) => [
                'label' => TaxService::label($tax->name, $tax->rate_basis_points),
                'amount' => $tax->food_amount + $tax->delivery_amount,
            ])->all(),
            'tax_inclusive' => (bool) $taxes->first()?->inclusive,
            'tin' => $taxes->isNotEmpty() ? $this->profile->tin() : null,
            'payment' => $this->paymentLabel($order),
            'message' => $this->profile->receiptMessage(),
            // A pharmacy's or shop's receipt carries its number as a barcode, scanned for a return (R3).
            'barcode' => app(BusinessType::class)->isRestaurant() ? null : $order->reference,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function kitchenTicket(Order $order): array
    {
        $order->loadMissing(['items.options', 'shift.terminal', 'tableTab.table']);

        return [
            'reference' => $order->reference,
            'placed_at' => $order->placed_at?->timezone('Africa/Accra'),
            'fulfilment' => $order->fulfilment_type,
            'scheduled_for' => $order->scheduled_for?->timezone('Africa/Accra'),
            'counter' => $order->shift?->terminal?->name,
            // Tables module: which table to take it to, and the bill it's a round of.
            'table' => $order->tableTab?->table?->name,
            'bill' => app(TableTabService::class)->roundOf($order),
            'lines' => $this->lines($order),
            'instructions' => $order->instructions,
        ];
    }

    /**
     * A table's whole bill (Tables module), in the receipt's shape: every
     * round's lines, totals and tax added up. Printed when the bill is
     * settled (or asked for before).
     *
     * @return array<string, mixed>
     */
    public function tabBill(TableTab $tab): array
    {
        $tab->loadMissing(['table', 'branch', 'shift.terminal', 'settledBy', 'orders.items.options', 'orders.taxes']);
        $rounds = app(TableTabService::class)->rounds($tab);
        $first = $rounds->first();

        $taxes = $rounds->flatMap(fn (Order $order) => $order->taxes)
            ->groupBy(fn ($tax) => $tax->name.'|'.$tax->rate_basis_points.'|'.(int) $tax->inclusive)
            ->map(fn ($group) => [
                'label' => TaxService::label($group->first()->name, $group->first()->rate_basis_points),
                'amount' => (int) $group->sum(fn ($tax) => $tax->food_amount + $tax->delivery_amount),
                'inclusive' => (bool) $group->first()->inclusive,
            ])->values();

        return [
            'business' => $this->profile->name(),
            'logo' => $this->profile->logoUrl(),
            'branch' => $tab->branch?->name,
            'address' => $tab->branch?->address,
            'phone' => $this->phoneDisplay((string) $tab->branch?->phone),
            // One number for the whole table (2026-11-20): the bill's own.
            'reference' => $tab->reference ?? __('Table :table', ['table' => $tab->table?->name]),
            'placed_at' => ($tab->settled_at ?? now())->clone()->timezone('Africa/Accra'),
            'served_by' => $tab->settledBy?->name,
            'counter' => $tab->shift?->terminal?->name,
            'table' => $tab->table?->name,
            'fulfilment' => 'dine_in',
            'customer' => $tab->customer_name,
            'bill' => null,
            'lines' => $rounds->flatMap(fn (Order $order) => $this->lines($order))->values()->all(),
            'subtotal' => (int) $rounds->sum('subtotal'),
            'discount' => (int) $rounds->sum('discount_total'),
            'delivery_fee' => 0,
            'total' => (int) $rounds->sum('total'),
            'taxes' => $taxes->map(fn ($tax) => ['label' => $tax['label'], 'amount' => $tax['amount']])->all(),
            'tax_inclusive' => (bool) ($taxes->first()['inclusive'] ?? false),
            'tin' => $taxes->isNotEmpty() ? $this->profile->tin() : null,
            // null: the bill isn't paid yet (printed before settling).
            'payment' => $tab->isOpen() ? null : ($first ? $this->paymentLabel($first) : '—'),
            'message' => $this->profile->receiptMessage(),
            'rounds' => $rounds->count(),
        ];
    }

    /**
     * @return list<array{name: string, quantity: int, total: int, free: bool, notes: ?string, options: list<string>}>
     */
    private function lines(Order $order): array
    {
        return $order->items->map(fn (OrderItem $item) => [
            'name' => $item->name_snapshot,
            'quantity' => (int) $item->quantity,
            'total' => (int) $item->line_total,
            'free' => (bool) $item->is_free,
            'notes' => $item->notes,
            'options' => $item->options->map(fn (OrderItemOption $option) => ($option->quantity > 1 ? $option->quantity.'× ' : '').$option->name_snapshot)->values()->all(),
        ])->values()->all();
    }

    private function servedBy(Order $order): ?string
    {
        $event = $order->events->sortBy('id')->first(fn ($event) => $event->actor_id && ! in_array($event->actor_type, ['customer', 'system', 'rider'], true));

        return $event ? User::withTrashed()->find($event->actor_id)?->name : null;
    }

    private function paymentLabel(Order $order): string
    {
        return match ($order->payment_method) {
            'cash' => __('Cash'),
            'momo' => __('Mobile money'),
            'paystack' => __('Paid online'),
            'bolt_food' => __('Bolt Food'),
            Order::COMPLIMENTARY => __('Freebie'),
            default => ucfirst((string) $order->payment_method),
        };
    }

    private function phoneDisplay(string $phone): string
    {
        return preg_match('/^\+233(\d{3})(\d{3})(\d{3})$/', $phone, $m) ? "0{$m[1]} {$m[2]} {$m[3]}" : $phone;
    }
}
