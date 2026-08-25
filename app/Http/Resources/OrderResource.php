<?php

namespace App\Http\Resources;

use App\Services\Branches\BranchContext;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Shared by the staff/manager dashboard (OrderDashboardController), order
 * actions (OrderActionController) and the rider dashboard
 * (Rider\DashboardController) — three audiences with different visibility.
 * Everyone gets the customer's name/phone and the semantic delivery
 * location (area, landmark, GhanaPost code); only a rider additionally
 * gets the raw lat/lng captured at checkout — staff and managers work off
 * the address, never the precise coordinate, which stays rider-only.
 */
class OrderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'status' => $this->status,
            'fulfilment_type' => $this->fulfilment_type,
            'payment_method' => $this->payment_method,
            'payment_status' => $this->payment_status,
            'total' => $this->total,
            'placed_at' => $this->placed_at?->toIso8601String(),
            // No dedicated preparing_at column — derived from order_events,
            // same pattern as EscalateUnacknowledgedOrders' paidAt. Drives
            // the dashboard's 30-minute cooking countdown (orders/
            // dashboard.blade.php); null whenever events isn't loaded or
            // the order never actually passed through 'preparing'.
            'preparing_at' => $this->whenLoaded(
                'events',
                fn () => $this->events
                    ->where('to_status', 'preparing')
                    ->sortByDesc('created_at')
                    ->first()?->created_at?->toIso8601String()
            ),
            'rider_id' => $this->rider_id,
            'rider_name' => $this->whenLoaded('rider', fn () => $this->rider?->name),
            'claimed_at' => $this->claimed_at?->toIso8601String(),
            // Already public (the customer-facing branches/contact pages
            // embed the same coordinate in a map) — no gating needed like
            // the customer's lat/lng below. Only included when eager-loaded
            // (rider dashboard); the staff/manager board doesn't load
            // 'branch' and simply won't have this key at all. Lets the
            // rider's "Get directions" link route from the branch to the
            // customer, not from wherever the rider's device currently
            // reports them to be.
            'branch' => $this->whenLoaded('branch', fn () => [
                'lat' => (float) $this->branch->lat,
                'lng' => (float) $this->branch->lng,
            ]),
            'delivery_address' => $this->fulfilment_type === 'delivery' ? [
                'area_name' => $this->delivery_address_snapshot['area_name'] ?? null,
                'landmark' => $this->delivery_address_snapshot['landmark'] ?? null,
                'ghanapost_code' => $this->delivery_address_snapshot['ghanapost_code'] ?? null,
                'lat' => $this->isRider($request) ? ($this->delivery_address_snapshot['lat'] ?? null) : null,
                'lng' => $this->isRider($request) ? ($this->delivery_address_snapshot['lng'] ?? null) : null,
            ] : null,
            'customer_name' => $this->whenLoaded('customer', fn () => $this->customer->name),
            'customer_phone' => $this->whenLoaded('customer', fn () => $this->customer->phone),
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn ($item) => [
                'name' => $item->name_snapshot,
                'quantity' => $item->quantity,
                'notes' => $item->notes,
                'options' => $item->relationLoaded('options')
                    ? $item->options->pluck('name_snapshot')
                    : [],
            ])),
        ];
    }

    /**
     * Not memoized — a static cache here would persist across requests on
     * a long-lived worker (Octane) or across test methods in the same
     * phpunit process, either of which risks serving a stale role
     * determination for a reused user+branch id. BranchContext::
     * primaryRoleFor()'s couple of queries per order is a non-issue at
     * this business's order volume (see WeeklySalesReportService's own
     * docblock on the same tradeoff).
     */
    private function isRider(Request $request): bool
    {
        $user = $request->user();

        return $user && app(BranchContext::class)->primaryRoleFor($user, $this->branch_id) === 'rider';
    }
}
