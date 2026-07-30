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
            'total' => $this->total,
            'cancellation_reason' => $this->cancellation_reason,
            'branch' => $this->whenLoaded('branch', fn () => [
                'name' => $this->branch->name,
                'phone' => $this->branch->phone,
                'address' => $this->branch->address,
            ]),
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn ($item) => [
                'name' => $item->name_snapshot,
                'quantity' => $item->quantity,
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
