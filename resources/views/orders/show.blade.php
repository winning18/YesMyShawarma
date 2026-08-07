<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <a href="{{ route('dashboard.orders.history', ['channel' => $order->channel]) }}" class="text-sm text-gray-500 hover:text-gray-700">
                    &larr; {{ __('Back to :channel order history', ['channel' => strtoupper($order->channel)]) }}
                </a>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight mt-1">{{ $order->reference }}</h2>
            </div>
            @php
                $badgeClass = match (true) {
                    $order->status === 'delivered' => 'bg-green-50 text-green-700',
                    in_array($order->status, ['cancelled', 'rejected', 'failed', 'abandoned', 'refunded'], true) => 'bg-red-50 text-red-700',
                    default => 'bg-gray-100 text-gray-600',
                };
            @endphp
            <span class="text-sm font-medium px-3 py-1.5 rounded-md capitalize {{ $badgeClass }}">{{ str_replace('_', ' ', $order->status) }}</span>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="lg:col-span-2 space-y-6">
                {{-- Items --}}
                <div class="bg-white shadow rounded-lg p-4">
                    <h3 class="font-semibold text-gray-800 mb-3">{{ __('Items') }}</h3>
                    <ul class="text-sm space-y-3">
                        @foreach ($order->items as $item)
                            <li>
                                <div class="flex justify-between">
                                    <span class="text-gray-800">{{ $item->quantity }}x {{ $item->name_snapshot }}</span>
                                    <span class="text-gray-800">GH₵{{ number_format($item->line_total / 100, 2) }}</span>
                                </div>
                                @foreach ($item->options as $option)
                                    <div class="flex justify-between text-gray-500 pl-4">
                                        <span>{{ $option->name_snapshot }}</span>
                                        <span>+GH₵{{ number_format($option->price_delta_snapshot / 100, 2) }}</span>
                                    </div>
                                @endforeach
                                @if ($item->notes)
                                    <p class="text-xs text-gray-500 pl-4 italic">{{ $item->notes }}</p>
                                @endif
                            </li>
                        @endforeach
                    </ul>

                    <div class="border-t border-gray-100 mt-4 pt-4 text-sm space-y-2">
                        <div class="flex justify-between">
                            <span class="text-gray-500">{{ __('Subtotal') }}</span>
                            <span class="text-gray-800">GH₵{{ number_format($order->subtotal / 100, 2) }}</span>
                        </div>
                        @if ($order->discount_total > 0)
                            <div class="flex justify-between">
                                <span class="text-gray-500">{{ __('Discount') }}{{ $order->promotion ? ' ('.$order->promotion->code.')' : '' }}</span>
                                <span class="text-gray-800">-GH₵{{ number_format($order->discount_total / 100, 2) }}</span>
                            </div>
                        @endif
                        @if ($order->fulfilment_type === 'delivery')
                            <div class="flex justify-between">
                                <span class="text-gray-500">{{ __('Delivery fee') }}</span>
                                <span class="text-gray-800">{{ $order->delivery_fee > 0 ? 'GH₵'.number_format($order->delivery_fee / 100, 2) : __('Not yet priced') }}</span>
                            </div>
                        @endif
                        <div class="flex justify-between font-semibold border-t border-gray-100 pt-2">
                            <span>{{ __('Total') }}</span>
                            <span>GH₵{{ number_format($order->total / 100, 2) }}</span>
                        </div>
                    </div>
                </div>

                {{-- Delivery details --}}
                @if ($order->fulfilment_type === 'delivery' && $order->delivery_address_snapshot)
                    <div class="bg-white shadow rounded-lg p-4">
                        <h3 class="font-semibold text-gray-800 mb-3">{{ __('Delivery') }}</h3>
                        <dl class="text-sm space-y-2">
                            <div class="flex justify-between">
                                <dt class="text-gray-500">{{ __('Area') }}</dt>
                                <dd class="text-gray-800">{{ $order->delivery_address_snapshot['area_name'] ?? '—' }}</dd>
                            </div>
                            <div class="flex justify-between">
                                <dt class="text-gray-500">{{ __('Landmark') }}</dt>
                                <dd class="text-gray-800">{{ $order->delivery_address_snapshot['landmark'] ?? '—' }}</dd>
                            </div>
                            @if (! empty($order->delivery_address_snapshot['ghanapost_code']))
                                <div class="flex justify-between">
                                    <dt class="text-gray-500">{{ __('GhanaPost GPS code') }}</dt>
                                    <dd class="text-gray-800">{{ $order->delivery_address_snapshot['ghanapost_code'] }}</dd>
                                </div>
                            @endif
                            <div class="flex justify-between">
                                <dt class="text-gray-500">{{ __('Rider') }}</dt>
                                <dd class="text-gray-800">{{ $order->rider->name ?? __('Not yet assigned') }}</dd>
                            </div>
                        </dl>
                    </div>
                @endif

                @if ($order->instructions)
                    <div class="bg-white shadow rounded-lg p-4">
                        <h3 class="font-semibold text-gray-800 mb-2">{{ __('Instructions') }}</h3>
                        <p class="text-sm text-gray-600">{{ $order->instructions }}</p>
                    </div>
                @endif

                {{-- Timeline --}}
                <div class="bg-white shadow rounded-lg p-4">
                    <h3 class="font-semibold text-gray-800 mb-3">{{ __('Timeline') }}</h3>
                    <ul class="text-sm space-y-2">
                        @forelse ($order->events as $event)
                            <li class="flex justify-between">
                                <span class="text-gray-800 capitalize">
                                    {{ str_replace('_', ' ', $event->to_status) }}
                                    <span class="text-gray-400">— {{ ucfirst($event->actor_type) }}</span>
                                </span>
                                <span class="text-gray-500">{{ $event->created_at->timezone('Africa/Accra')->format('d M Y, H:i') }}</span>
                            </li>
                        @empty
                            <li class="text-gray-500">{{ __('No events recorded.') }}</li>
                        @endforelse
                    </ul>
                </div>
            </div>

            <div class="space-y-6">
                {{-- Customer --}}
                <div class="bg-white shadow rounded-lg p-4">
                    <h3 class="font-semibold text-gray-800 mb-3">{{ __('Customer') }}</h3>
                    <dl class="text-sm space-y-2">
                        <div>
                            <dt class="text-gray-500">{{ __('Name') }}</dt>
                            <dd class="text-gray-800">{{ $order->customer->name ?? __('(no name)') }}</dd>
                        </div>
                        <div>
                            <dt class="text-gray-500">{{ __('Phone') }}</dt>
                            <dd class="text-gray-800">{{ $order->customer->phone }}</dd>
                        </div>
                        @if ($order->customer->email)
                            <div>
                                <dt class="text-gray-500">{{ __('Email') }}</dt>
                                <dd class="text-gray-800">{{ $order->customer->email }}</dd>
                            </div>
                        @endif
                    </dl>
                    @can('customers.view')
                        <a href="{{ route('dashboard.customers.index', ['search' => $order->customer->phone]) }}" class="inline-block mt-3 text-sm text-indigo-600 hover:underline">
                            {{ __('View customer history →') }}
                        </a>
                    @endcan
                </div>

                {{-- Order info --}}
                <div class="bg-white shadow rounded-lg p-4">
                    <h3 class="font-semibold text-gray-800 mb-3">{{ __('Order info') }}</h3>
                    <dl class="text-sm space-y-2">
                        <div class="flex justify-between">
                            <dt class="text-gray-500">{{ __('Branch') }}</dt>
                            <dd class="text-gray-800">{{ $order->branch->name }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-gray-500">{{ __('Channel') }}</dt>
                            <dd class="text-gray-800 uppercase">{{ $order->channel }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-gray-500">{{ __('Fulfilment') }}</dt>
                            <dd class="text-gray-800 capitalize">{{ $order->fulfilment_type }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-gray-500">{{ __('Payment method') }}</dt>
                            <dd class="text-gray-800 capitalize">{{ $order->payment_method }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-gray-500">{{ __('Payment status') }}</dt>
                            <dd class="text-gray-800 capitalize">{{ $order->payment_status }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-gray-500">{{ __('Placed') }}</dt>
                            <dd class="text-gray-800">{{ $order->placed_at?->timezone('Africa/Accra')->format('d M Y, H:i') }}</dd>
                        </div>
                        @if ($order->cancellation_reason)
                            <div>
                                <dt class="text-gray-500">{{ __('Cancellation reason') }}</dt>
                                <dd class="text-gray-800">{{ $order->cancellation_reason }}</dd>
                            </div>
                        @endif
                    </dl>
                </div>

                @if ($order->payments->isNotEmpty())
                    <div class="bg-white shadow rounded-lg p-4">
                        <h3 class="font-semibold text-gray-800 mb-3">{{ __('Payments') }}</h3>
                        <ul class="text-sm space-y-2">
                            @foreach ($order->payments as $payment)
                                <li class="flex justify-between">
                                    <span class="text-gray-800 capitalize">{{ $payment->provider }}</span>
                                    <span class="text-gray-500 capitalize">{{ $payment->status }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
