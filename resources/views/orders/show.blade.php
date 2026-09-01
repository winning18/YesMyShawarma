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
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @if (session('status'))
                <div class="rounded-md bg-green-50 text-green-700 text-sm px-4 py-2">{{ session('status') }}</div>
            @endif

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
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
                                <dd class="text-gray-800">{{ $order->delivery_address_snapshot['area_name'] ?? 'N/A' }}</dd>
                            </div>
                            <div class="flex justify-between">
                                <dt class="text-gray-500">{{ __('Landmark') }}</dt>
                                <dd class="text-gray-800">{{ $order->delivery_address_snapshot['landmark'] ?? 'N/A' }}</dd>
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
                                    <span class="text-gray-400">({{ ucfirst($event->actor_type) }})</span>
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

                    @can('confirmMomoPayment', $order)
                        @if ($order->payment_method === 'momo' && $order->payment_status === 'pending')
                            <form
                                method="POST" action="{{ route('orders.confirm_momo_payment', $order) }}"
                                class="mt-4 pt-4 border-t border-gray-100 space-y-2"
                            >
                                @csrf
                                <label for="transaction_id" class="block text-xs font-medium text-gray-500">
                                    {{ __('Momo transaction ID') }}
                                </label>
                                <input
                                    type="text" name="transaction_id" id="transaction_id" required
                                    class="w-full rounded-md border-gray-300 text-sm"
                                    placeholder="{{ __('e.g. from the Momo confirmation SMS') }}"
                                >
                                <x-input-error :messages="$errors->get('transaction_id')" class="mt-1" />
                                <button type="submit" class="w-full px-4 py-2 bg-gray-800 text-white text-sm font-semibold rounded-md hover:bg-gray-900">
                                    {{ __('Mark paid') }}
                                </button>
                            </form>
                        @endif
                    @endcan
                </div>

                @if ($order->payments->isNotEmpty())
                    <div class="bg-white shadow rounded-lg p-4">
                        <h3 class="font-semibold text-gray-800 mb-3">{{ __('Payments') }}</h3>
                        <ul class="text-sm space-y-2">
                            @foreach ($order->payments as $payment)
                                <li class="flex justify-between gap-2">
                                    <span class="text-gray-800 capitalize">
                                        {{ $payment->provider }}
                                        @if ($payment->provider_reference)
                                            <span class="text-gray-400 font-normal">({{ $payment->provider_reference }})</span>
                                        @endif
                                    </span>
                                    <span class="text-gray-500 capitalize shrink-0">{{ $payment->status }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @can('requestRefund', $order)
                    <div class="bg-white shadow rounded-lg p-4">
                        <h3 class="font-semibold text-gray-800 mb-3">{{ __('Refunds') }}</h3>

                        @forelse ($order->refunds as $refund)
                            @php
                                $badgeClass = match ($refund->status) {
                                    'completed' => 'bg-green-50 text-green-700',
                                    'denied' => 'bg-red-50 text-red-700',
                                    'approved' => 'bg-amber-50 text-amber-700',
                                    default => 'bg-gray-100 text-gray-600',
                                };
                            @endphp
                            <div class="text-sm py-2 {{ ! $loop->last ? 'border-b border-gray-100' : '' }}">
                                <div class="flex items-center justify-between">
                                    <span class="text-gray-800">GH₵{{ number_format($refund->amount / 100, 2) }}</span>
                                    <span class="text-xs font-medium px-2 py-1 rounded-md capitalize {{ $badgeClass }}">{{ $refund->status }}</span>
                                </div>
                                <p class="text-gray-500 text-xs mt-1">{{ $refund->reason }}</p>
                                <p class="text-gray-400 text-xs mt-1">
                                    {{ __('Requested by :name on :date', ['name' => $refund->requestedBy->name, 'date' => $refund->created_at->timezone('Africa/Accra')->format('d M Y, H:i')]) }}
                                </p>

                                @can('complete', $refund)
                                    <form method="POST" action="{{ route('dashboard.refunds.complete', $refund) }}" class="mt-2">
                                        @csrf
                                        <button type="submit" class="text-xs font-semibold text-gray-800 hover:underline">{{ __('Complete refund →') }}</button>
                                    </form>
                                @endcan
                            </div>
                        @empty
                            <p class="text-sm text-gray-400 mb-3">{{ __('No refunds recorded.') }}</p>
                        @endforelse

                        @can('requestRefund', $order)
                            @if ($remainingRefundBalance > 0)
                                <form method="POST" action="{{ route('orders.refunds.store', $order) }}" class="mt-4 pt-4 border-t border-gray-100 space-y-2">
                                    @csrf
                                    <p class="text-xs text-gray-500">
                                        {{ __('Up to GH₵:amount refundable.', ['amount' => number_format($remainingRefundBalance / 100, 2)]) }}
                                    </p>
                                    <label for="amount" class="block text-xs font-medium text-gray-500">{{ __('Amount (GH₵)') }}</label>
                                    <input
                                        type="number" step="0.01" min="0.01" max="{{ $remainingRefundBalance / 100 }}"
                                        name="amount" id="amount" required value="{{ old('amount') }}"
                                        class="w-full rounded-md border-gray-300 text-sm"
                                    >
                                    <x-input-error :messages="$errors->get('amount')" class="mt-1" />

                                    <label for="reason" class="block text-xs font-medium text-gray-500">{{ __('Reason') }}</label>
                                    <textarea
                                        name="reason" id="reason" rows="2" required
                                        class="w-full rounded-md border-gray-300 text-sm"
                                    >{{ old('reason') }}</textarea>
                                    <x-input-error :messages="$errors->get('reason')" class="mt-1" />

                                    <button type="submit" class="w-full px-4 py-2 bg-gray-800 text-white text-sm font-semibold rounded-md hover:bg-gray-900">
                                        {{ $canRefundDirectly ? __('Refund') : __('Request refund') }}
                                    </button>
                                </form>
                            @endif
                        @endcan
                    </div>
                @endcan
            </div>
            </div>
        </div>
    </div>
</x-app-layout>
