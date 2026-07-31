<x-customer-layout :title="'Order confirmed · '.config('app.name')">
    <div class="max-w-xl mx-auto text-center">
        <div class="w-16 h-16 mx-auto rounded-full bg-brand-yellow flex items-center justify-center">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-8 h-8 text-brand-black" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
                <path d="M20 6 9 17l-5-5" />
            </svg>
        </div>

        <h1 class="text-2xl font-bold mt-4">
            {{ __('Thank you, :name!', ['name' => $order->customer->name ?? __('there')]) }}
        </h1>
        <p class="text-brand-gray-500 mt-1">
            @if ($order->fulfilment_type === 'delivery')
                {{ __("We've received your order and will get it on its way soon.") }}
            @else
                {{ __("We've received your order and will start preparing it right away.") }}
            @endif
        </p>

        <p class="text-sm text-brand-gray-500 mt-4">{{ __('Order reference') }}</p>
        <p class="text-lg font-semibold">{{ $order->reference }}</p>
    </div>

    <div class="max-w-xl mx-auto mt-8 border border-brand-gray-100 rounded-lg p-6 text-left">
        <h2 class="font-semibold mb-3">{{ __('Order summary') }}</h2>
        <p class="text-sm text-brand-gray-500 mb-3">{{ $order->branch->name }}</p>

        <ul class="text-sm space-y-3 mb-4">
            @foreach ($order->items as $item)
                <li>
                    <div class="flex justify-between">
                        <span>{{ $item->quantity }}x {{ $item->name_snapshot }}</span>
                        <span>GH₵{{ number_format($item->line_total / 100, 2) }}</span>
                    </div>
                    @foreach ($item->options as $option)
                        <div class="flex justify-between text-brand-gray-500 pl-4">
                            <span>{{ $option->name_snapshot }}</span>
                            <span>+GH₵{{ number_format($option->price_delta_snapshot / 100, 2) }}</span>
                        </div>
                    @endforeach
                </li>
            @endforeach
        </ul>

        <div class="flex justify-between">
            <span>{{ __('Subtotal') }}</span>
            <span>GH₵{{ number_format($order->subtotal / 100, 2) }}</span>
        </div>

        @if ($order->discount_total > 0)
            <div class="flex justify-between text-sm mt-2">
                <span>{{ __('Discount') }}</span>
                <span>-GH₵{{ number_format($order->discount_total / 100, 2) }}</span>
            </div>
        @endif

        @if ($order->fulfilment_type === 'delivery')
            <div class="flex justify-between text-sm mt-2">
                <span>{{ __('Delivery fee') }}</span>
                @if ($order->delivery_fee > 0)
                    <span>GH₵{{ number_format($order->delivery_fee / 100, 2) }}</span>
                @else
                    <span class="text-brand-gray-500">{{ __('Calculated on arrival') }}</span>
                @endif
            </div>
        @endif

        <div class="flex justify-between font-semibold border-t border-brand-gray-100 pt-3 mt-2">
            <span>{{ __('Total') }}</span>
            <span>GH₵{{ number_format($order->total / 100, 2) }}</span>
        </div>

        <div class="border-t border-brand-gray-100 mt-4 pt-4 text-sm text-brand-gray-500 space-y-1">
            <div class="flex justify-between">
                <span>{{ __('Fulfilment') }}</span>
                <span class="text-brand-black">{{ $order->fulfilment_type === 'delivery' ? __('Delivery') : __('Pickup') }}</span>
            </div>
            @if ($order->fulfilment_type === 'delivery' && $order->delivery_address_snapshot)
                <div class="flex justify-between">
                    <span>{{ __('Area') }}</span>
                    <span class="text-brand-black">{{ $order->delivery_address_snapshot['area_name'] ?? '—' }}</span>
                </div>
                @if (! empty($order->delivery_address_snapshot['landmark']))
                    <div class="flex justify-between">
                        <span>{{ __('Landmark') }}</span>
                        <span class="text-brand-black">{{ $order->delivery_address_snapshot['landmark'] }}</span>
                    </div>
                @endif
            @endif
            <div class="flex justify-between">
                <span>{{ __('Payment method') }}</span>
                <span class="text-brand-black">
                    {{ $order->payment_method === 'paystack' ? __('Pay now (card / mobile money)') : __('Cash on delivery / pickup') }}
                </span>
            </div>
            @if ($order->customer->phone ?? null)
                <div class="flex justify-between">
                    <span>{{ __('Phone number') }}</span>
                    <span class="text-brand-black">{{ $order->customer->phone }}</span>
                </div>
            @endif
        </div>
    </div>

    <div class="max-w-xl mx-auto mt-8 flex flex-col sm:flex-row gap-3">
        <a
            href="{{ route('tracking.show', $order) }}"
            class="flex-1 text-center px-6 py-3 bg-brand-yellow text-brand-black font-semibold rounded-md hover:bg-brand-yellow-dark"
        >
            {{ __('Track your order') }}
        </a>
        <a
            href="{{ route('menu.index') }}"
            class="flex-1 text-center px-6 py-3 border border-brand-gray-300 font-semibold rounded-md hover:bg-brand-gray-100"
        >
            {{ __('Back to menu') }}
        </a>
    </div>
</x-customer-layout>
