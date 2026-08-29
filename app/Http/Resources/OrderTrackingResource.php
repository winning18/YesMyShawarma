<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderTrackingResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'reference' => $this->reference,
            'status' => $this->status,
            'fulfilment_type' => $this->fulfilment_type,
            'subtotal' => $this->subtotal,
            'discount_total' => $this->discount_total,
            'delivery_fee' => $this->delivery_fee,
            'total' => $this->total,
            'payment_method' => $this->payment_method,
            'delivery_address' => $this->fulfilment_type === 'delivery' ? [
                'area_name' => $this->delivery_address_snapshot['area_name'] ?? null,
                'landmark' => $this->delivery_address_snapshot['landmark'] ?? null,
            ] : null,
            'cancellation_reason' => $this->cancellation_reason,
            // Pickup orders never reach "delivered" — see ReviewService's
            // isEligible() docblock for why this isn't just
            // status === 'delivered'. Never expose the review's moderation
            // status to the customer — they only ever see submitted vs
            // not, never whether staff approved or rejected it.
            'review_eligible' => $this->status === 'delivered'
                || ($this->fulfilment_type === 'pickup' && $this->status === 'dispatched'),
            'review' => $this->whenLoaded('review', fn () => $this->review ? [
                'rating' => $this->review->rating,
                'comment' => $this->review->comment,
            ] : null),
            'branch' => $this->whenLoaded('branch', fn () => [
                'name' => $this->branch->name,
                'phone' => $this->branch->phone,
                'address' => $this->branch->address,
            ]),
            'customer' => $this->whenLoaded('customer', fn () => [
                'name' => $this->customer->name,
                'phone' => $this->customer->phone,
            ]),
            'rider' => $this->whenLoaded('rider', fn () => $this->rider ? [
                'name' => $this->rider->name,
                'phone' => $this->rider->phone,
            ] : null),
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn ($item) => [
                'name' => $item->name_snapshot,
                'quantity' => $item->quantity,
                'line_total' => $item->line_total,
                'image_url' => $item->menuItem?->imageUrl(),
                'options' => $item->relationLoaded('options') ? $item->options->map(fn ($option) => [
                    'name' => $option->name_snapshot,
                    'price_delta' => $option->price_delta_snapshot,
                ]) : [],
            ])),
            'timeline' => [
                'placed_at' => $this->placed_at?->toIso8601String(),
                'accepted_at' => $this->accepted_at?->toIso8601String(),
                'ready_at' => $this->ready_at?->toIso8601String(),
                'dispatched_at' => $this->dispatched_at?->toIso8601String(),
                'delivered_at' => $this->delivered_at?->toIso8601String(),
                'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            ],
        ];
    }
}
