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

        {{--
            +/- reloads the page once the PATCH confirms, rather than
            recomputing the line locally — a buy_x_get_y_free promotion's
            free line (a whole extra block below, not just a number) can
            appear, disappear, or change quantity based on ANY sibling
            line of the same item, and this page renders each line as its
            own static Blade block rather than a reactive loop, so there's
            no in-place way to add/remove one from the client side. A
            brief "Updating…" state covers the round trip.
        --}}
        <div x-data="cartPage()" class="grid grid-cols-1 md:grid-cols-3 gap-8" :class="{ 'opacity-50 pointer-events-none': loading }">
            <div class="md:col-span-2 space-y-4">
                @foreach ($lines as $line)
                    @if ($line['is_free'] ?? false)
                        <div class="border border-green-200 bg-green-50 rounded-lg p-5 flex justify-between items-center gap-4">
                            <div>
                                <p class="font-semibold">
                                    {{ $line['name_snapshot'] }}
                                    <span class="ml-1 text-xs font-semibold text-green-700 bg-white rounded px-1.5 py-0.5 align-middle">{{ __('FREE') }}</span>
                                </p>
                                <p class="text-sm text-brand-gray-500">{{ __('Qty :quantity — added automatically', ['quantity' => $line['quantity']]) }}</p>
                            </div>
                            <p class="font-semibold text-green-700 shrink-0">{{ __('FREE') }}</p>
                        </div>
                    @else
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
                                        <div class="text-sm text-brand-gray-500 flex items-center gap-2">
                                            <span>{{ $option['name_snapshot'] }} (+GH₵{{ number_format($option['price_delta_snapshot'] / 100, 2) }})</span>
                                            @if ($option['adjustable'])
                                                <div class="inline-flex items-center gap-1">
                                                    <button
                                                        type="button" @click="changeOptionQuantity('{{ $line['line_id'] }}', {{ $option['option_id'] }}, {{ $option['quantity'] }}, -1)"
                                                        class="w-5 h-5 flex items-center justify-center border border-brand-gray-300 rounded text-xs text-brand-gray-600 hover:bg-brand-gray-100"
                                                        aria-label="{{ __('Decrease quantity') }}"
                                                    >&minus;</button>
                                                    <span class="w-4 text-center text-xs">{{ $option['quantity'] }}</span>
                                                    <button
                                                        type="button" @click="changeOptionQuantity('{{ $line['line_id'] }}', {{ $option['option_id'] }}, {{ $option['quantity'] }}, 1)"
                                                        class="w-5 h-5 flex items-center justify-center border border-brand-gray-300 rounded text-xs text-brand-gray-600 hover:bg-brand-gray-100"
                                                        aria-label="{{ __('Increase quantity') }}"
                                                    >+</button>
                                                </div>
                                            @endif
                                        </div>
                                    @endforeach
                                    @if ($line['notes'])
                                        <p class="text-sm text-brand-gray-500 italic">{{ $line['notes'] }}</p>
                                    @endif

                                    <div class="mt-2 flex items-center gap-2">
                                        <label class="text-sm text-brand-gray-500">{{ __('Qty') }}</label>
                                        <div class="inline-flex items-center border border-brand-gray-300 rounded-md">
                                            <button
                                                type="button" @click="changeQuantity('{{ $line['line_id'] }}', {{ $line['quantity'] }}, -1)" :disabled="{{ $line['quantity'] }} <= 1"
                                                class="w-7 h-7 flex items-center justify-center text-brand-gray-600 disabled:opacity-30 hover:bg-brand-gray-100"
                                                aria-label="{{ __('Decrease quantity') }}"
                                            >&minus;</button>
                                            <span class="w-8 text-center text-sm">{{ $line['quantity'] }}</span>
                                            <button
                                                type="button" @click="changeQuantity('{{ $line['line_id'] }}', {{ $line['quantity'] }}, 1)" :disabled="{{ $line['quantity'] }} >= {{ \App\Services\Cart\CartService::MAX_LINE_QUANTITY }}"
                                                class="w-7 h-7 flex items-center justify-center text-brand-gray-600 disabled:opacity-30 hover:bg-brand-gray-100"
                                                aria-label="{{ __('Increase quantity') }}"
                                            >+</button>
                                        </div>
                                    </div>
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
                    @endif
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

    <script>
        function cartPage() {
            return {
                loading: false,

                changeQuantity(lineId, currentQuantity, delta) {
                    const max = {{ \App\Services\Cart\CartService::MAX_LINE_QUANTITY }};
                    const newQuantity = Math.min(max, Math.max(1, currentQuantity + delta));
                    if (newQuantity === currentQuantity || this.loading) return;

                    this.patch('/cart/' + lineId, { quantity: newQuantity });
                },

                changeOptionQuantity(lineId, optionId, currentQuantity, delta) {
                    const max = {{ \App\Services\Menu\MenuPricingService::MAX_OPTION_QUANTITY }};
                    const newQuantity = Math.min(max, Math.max(1, currentQuantity + delta));
                    if (newQuantity === currentQuantity || this.loading) return;

                    this.patch(`/cart/${lineId}/options/${optionId}`, { quantity: newQuantity });
                },

                // Reloads on success rather than patching the DOM in place
                // — see this section's own comment above for why a
                // buy_x_get_y_free free line can't be kept in sync any
                // other way on this page.
                patch(url, body) {
                    this.loading = true;

                    fetch(url, {
                        method: 'PATCH',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                        },
                        body: JSON.stringify(body),
                    }).then((response) => {
                        if (response.ok) {
                            window.location.reload();
                        } else {
                            this.loading = false;
                        }
                    }).catch(() => {
                        this.loading = false;
                    });
                },
            };
        }
    </script>
</x-customer-layout>
