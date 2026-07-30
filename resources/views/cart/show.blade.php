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

        <div class="space-y-4">
            @foreach ($lines as $line)
                <div class="border border-brand-gray-100 rounded-lg p-5 flex justify-between items-start gap-4">
                    <div>
                        <p class="font-semibold">{{ $line['name_snapshot'] }}</p>
                        @if (! empty($line['options']))
                            <p class="text-sm text-brand-gray-500">
                                {{ collect($line['options'])->pluck('name_snapshot')->join(', ') }}
                            </p>
                        @endif
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

        <div class="mt-6 flex justify-between items-center border-t border-brand-gray-100 pt-4">
            <p class="font-semibold text-lg">{{ __('Subtotal') }}</p>
            <p class="font-semibold text-lg">GH₵{{ number_format($subtotal / 100, 2) }}</p>
        </div>

        <a
            href="{{ route('checkout.show') }}"
            class="mt-6 block text-center px-6 py-3 bg-brand-yellow text-brand-black font-semibold rounded-md hover:bg-brand-yellow-dark"
        >
            {{ __('Proceed to checkout') }}
        </a>
    @endif
</x-customer-layout>
