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
            +/- adjusts quantity instantly, no separate "Update" click. The
            line total and subtotal are recalculated client-side the moment
            a button is pressed (unit price × quantity is linear — no
            quantity-tiered discounts exist, see CLAUDE.md's promo scope —
            so this is exact, not an estimate) while a PATCH fires in the
            background to keep the session cart in sync. redirect: 'manual'
            stops fetch from following cart.update's redirect response,
            since nothing here reads it anyway.
        --}}
        <div
            x-data="cartPage(@js(array_map(fn ($line) => ['id' => $line['line_id'], 'quantity' => $line['quantity'], 'lineTotal' => $line['line_total']], $lines)))"
            class="grid grid-cols-1 md:grid-cols-3 gap-8"
        >
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

                                <div class="mt-2 flex items-center gap-2" x-data="{ line: lineFor('{{ $line['line_id'] }}') }">
                                    <label class="text-sm text-brand-gray-500">{{ __('Qty') }}</label>
                                    <div class="inline-flex items-center border border-brand-gray-300 rounded-md">
                                        <button
                                            type="button" @click="changeQuantity(line, -1)" :disabled="line.quantity <= 1"
                                            class="w-7 h-7 flex items-center justify-center text-brand-gray-600 disabled:opacity-30 hover:bg-brand-gray-100"
                                            aria-label="{{ __('Decrease quantity') }}"
                                        >&minus;</button>
                                        <span class="w-8 text-center text-sm" x-text="line.quantity">{{ $line['quantity'] }}</span>
                                        <button
                                            type="button" @click="changeQuantity(line, 1)" :disabled="line.quantity >= {{ \App\Services\Cart\CartService::MAX_LINE_QUANTITY }}"
                                            class="w-7 h-7 flex items-center justify-center text-brand-gray-600 disabled:opacity-30 hover:bg-brand-gray-100"
                                            aria-label="{{ __('Increase quantity') }}"
                                        >+</button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="text-right shrink-0">
                            <p class="font-semibold" x-text="formatMoney(lineFor('{{ $line['line_id'] }}').lineTotal)">GH₵{{ number_format($line['line_total'] / 100, 2) }}</p>
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
                        <p class="font-semibold text-lg" x-text="formatMoney(subtotal)">GH₵{{ number_format($subtotal / 100, 2) }}</p>
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
        function cartPage(initialLines) {
            return {
                lines: initialLines,

                lineFor(id) {
                    return this.lines.find((line) => line.id === id);
                },

                get subtotal() {
                    return this.lines.reduce((sum, line) => sum + line.lineTotal, 0);
                },

                formatMoney(pesewas) {
                    return 'GH₵' + (pesewas / 100).toFixed(2);
                },

                changeQuantity(line, delta) {
                    const max = {{ \App\Services\Cart\CartService::MAX_LINE_QUANTITY }};
                    const newQuantity = Math.min(max, Math.max(1, line.quantity + delta));
                    if (newQuantity === line.quantity) return;

                    const unitBase = line.lineTotal / line.quantity;
                    line.quantity = newQuantity;
                    line.lineTotal = Math.round(unitBase * newQuantity);

                    fetch('/cart/' + line.id, {
                        method: 'PATCH',
                        redirect: 'manual',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                        },
                        body: JSON.stringify({ quantity: newQuantity }),
                    }).catch(() => {
                        // Best-effort — the next full page load re-syncs from
                        // the session either way.
                    });
                },
            };
        }
    </script>
</x-customer-layout>
