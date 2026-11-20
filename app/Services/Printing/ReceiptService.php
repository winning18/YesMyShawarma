<?php

namespace App\Services\Printing;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderItemOption;
use App\Models\User;
use App\Services\Settings\BusinessProfile;
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
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function kitchenTicket(Order $order): array
    {
        $order->loadMissing(['items.options', 'shift.terminal']);

        return [
            'reference' => $order->reference,
            'placed_at' => $order->placed_at?->timezone('Africa/Accra'),
            'fulfilment' => $order->fulfilment_type,
            'scheduled_for' => $order->scheduled_for?->timezone('Africa/Accra'),
            'counter' => $order->shift?->terminal?->name,
            'lines' => $this->lines($order),
            'instructions' => $order->instructions,
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
