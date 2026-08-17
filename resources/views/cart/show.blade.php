<x-customer-layout title="Cart · {{ config('app.name') }}">
    <h1 class="text-2xl font-bold mb-6">{{ __('Your cart') }}</h1>

    @foreach ($dropped as $message)
        <div class="mb-4 rounded-lg bg-brand-red-light border border-brand-red text-brand-red-dark text-sm px-4 py-2">
            {{ $message }}
        </div>
    @endforeach

    @if (empty($lines))
        <p class="text-brand-gray-500">{{ __('Your cart is empty.') }}</p>
        <a href="{{ route('menu.index') }}" class="inline-block mt-4 text-sm font-semibold text-brand-red hover:text-brand-red-dark">
            {{ __('Browse the menu →') }}
        </a>
    @else
        <p class="text-sm text-brand-gray-500 mb-4">{{ $branch->name }}</p>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
            <div class="md:col-span-2 space-y-4">
                @foreach ($lines as $line)
                    <div class="border border-brand-gray-100 rounded-lg p-5 flex justify-between items-start gap-4">
                        <div class="flex items-start gap-4 min-w-0">
                            @if ($line['image_url'])
                                <img src="{{ $line['image_url'] }}" alt="{{ $line['name_snapshot'] }}" class="w-16 h-16 rounded-md object-cover shrink-0">
                            @else
                                <div class="w-16 h-16 rounded-md bg-brand-gray-100 flex items-center justify-center shrink-0" role="img" aria-label="{{ $line['name_snapshot'] }}">
                                    <svg viewBox="0 0 24 24" fill="none" class="w-6 h-6 text-brand-gray-300">
                                        <path d="M4 7a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V7Z" stroke="currentColor" stroke-width="1.5" />
                                        <circle cx="9" cy="10.5" r="1.5" stroke="currentColor" stroke-width="1.5" />
                                        <path d="m5 16 4.5-4 3 2.5L16 11l3 3.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                                    </svg>
                                </div>
                            @endif

                            <div class="min-w-0">
                                <p class="font-semibold">{{ $line['name_snapshot'] }}</p>
                                @foreach ($line['options'] as $option)
                                    <p class="text-sm text-brand-gray-500">
                                        {{ $option['name_snapshot'] }} (+GH₵{{ number_format($option['price_delta_snapshot'] / 100, 2) }})
                                    </p>
                                @endforeach
                                @if ($line['notes'])
                                    <p class="text-sm text-brand-gray-500 italic">{{ $line['notes'] }}</p>
                                @endif

                                <form method="POST" action="{{ route('cart.update', $line['line_id']) }}" class="mt-2 flex items-center gap-2">
                                    @csrf
                                    @method('PATCH')
                                    <label class="text-sm text-brand-gray-500">{{ __('Qty') }}</label>
                                    <input type="number" name="quantity" value="{{ $line['quantity'] }}" min="1" max="{{ \App\Services\Cart\CartService::MAX_LINE_QUANTITY }}" class="w-16 rounded-md border-brand-gray-300 text-sm">
                                    <button type="submit" class="text-sm underline text-brand-gray-500">{{ __('Update') }}</button>
                                </form>
                            </div>
                        </div>

                        <div class="text-right shrink-0">
                            <p class="font-semibold">GH₵{{ number_format($line['line_total'] / 100, 2) }}</p>
                            <form method="POST" action="{{ route('cart.remove', $line['line_id']) }}" class="mt-2">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-sm text-brand-red hover:text-brand-red-dark">{{ __('Remove') }}</button>
                            </form>
                        </div>
                    </div>
                @endforeach
            </div>

            <div>
                <div class="border border-brand-gray-100 rounded-lg p-5 md:sticky md:top-24">
                    <div class="flex justify-between items-center mb-4">
                        <p class="font-semibold text-lg">{{ __('Subtotal') }}</p>
                        <p class="font-semibold text-lg">GH₵{{ number_format($subtotal / 100, 2) }}</p>
                    </div>

                    <a
                        href="{{ route('checkout.show') }}"
                        class="block text-center px-6 py-3 bg-brand-yellow text-brand-black font-semibold rounded-md hover:bg-brand-yellow-dark"
                    >
                        {{ __('Proceed to checkout') }}
                    </a>
                </div>
            </div>
        </div>
    @endif
</x-customer-layout>
