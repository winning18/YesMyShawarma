<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

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
}
