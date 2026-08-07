<x-customer-layout title="{{ $item->name }} · {{ config('app.name') }}">
    <a href="{{ route('menu.index') }}" class="text-sm underline text-brand-gray-500">&larr; {{ __('Back to menu') }}</a>

    @if ($errors->any())
        <div class="mt-6 rounded-lg bg-brand-red-light border border-brand-red text-brand-red-dark text-sm px-4 py-2">
            {{ $errors->first() }}
        </div>
    @endif

    {{-- The selected product --}}
    <div class="grid grid-cols-1 md:grid-cols-2 gap-10 mt-6 mb-12">
        <x-product-image :item="$item" class="w-full aspect-square rounded-xl" />

        <div>
            <h1 class="text-2xl font-bold mb-2">{{ $item->name }}</h1>

            @if ($item->description)
                <p class="text-brand-gray-500 mb-4">{{ $item->description }}</p>
            @endif

            <p class="text-2xl font-bold mb-6">
                GH₵{{ number_format($item->base_price / 100, 2) }}
            </p>

            <form method="POST" action="{{ route('cart.add') }}">
                @csrf
                <input type="hidden" name="branch_id" value="{{ $branch->id }}">
                <input type="hidden" name="menu_item_id" value="{{ $item->id }}">

                {{--
                    Extras stay inside this same form (they change this
                    line's own price) and only render at all when the item
                    actually has option groups — Drinks below are separate,
                    self-priced products — each gets its own tiny form and
                    becomes its own cart line, not an option on this one.
                --}}
                @if ($item->optionGroups->isNotEmpty())
                    <div class="space-y-4 mb-6">
                        @foreach ($item->optionGroups as $optionGroup)
                            <fieldset>
                                <legend class="text-sm text-brand-gray-500 mb-1.5">{{ $optionGroup->name }}</legend>
                                <div class="grid grid-cols-2 gap-x-3 gap-y-1.5">
                                    @foreach ($optionGroup->options as $option)
                                        <label class="flex items-center gap-1.5 text-sm">
                                            <input type="checkbox" name="option_ids[]" value="{{ $option->id }}" class="checkbox-check-black shrink-0 rounded border-brand-gray-300 text-brand-yellow focus:ring-brand-yellow">
                                            <span>{{ $option->name }} (+GH₵{{ number_format($option->price_delta / 100, 2) }})</span>
                                        </label>
                                    @endforeach
                                </div>
                            </fieldset>
                        @endforeach
                    </div>
                @endif

                <div class="flex items-center gap-3 mb-6">
                    <label class="text-sm font-medium">{{ __('Qty') }}</label>
                    <input type="number" name="quantity" value="1" min="1" max="{{ \App\Services\Cart\CartService::MAX_LINE_QUANTITY }}" class="w-20 rounded-md border-brand-gray-300 focus:border-brand-yellow focus:ring-brand-yellow">
                </div>

                <button type="submit" class="w-full sm:w-auto px-8 py-3 bg-brand-yellow text-brand-black font-bold rounded-md hover:bg-brand-yellow-dark">
                    {{ __('Add to cart') }}
                </button>
            </form>
        </div>
    </div>

    {{-- Drinks — optional add-ons, only rendered when any are available --}}
    @if ($drinks->isNotEmpty())
        <section>
            <h2 class="text-xl font-bold mb-4">{{ __('Drinks') }}</h2>

            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-4">
                @foreach ($drinks as $drink)
                    <div class="border border-brand-gray-100 rounded-lg p-3 text-center">
                        <x-product-image :item="$drink" class="w-full aspect-square rounded-md mb-2" />
                        <p class="text-sm font-semibold truncate">{{ $drink->name }}</p>
                        <p class="text-sm text-brand-gray-500 mb-2">
                            GH₵{{ number_format($drink->base_price / 100, 2) }}
                        </p>
                        <form method="POST" action="{{ route('cart.add') }}">
                            @csrf
                            <input type="hidden" name="branch_id" value="{{ $branch->id }}">
                            <input type="hidden" name="menu_item_id" value="{{ $drink->id }}">
                            <input type="hidden" name="quantity" value="1">
                            <button type="submit" class="w-full px-3 py-1.5 bg-brand-black text-brand-white text-xs font-semibold rounded-md hover:bg-brand-gray-700">
                                {{ __('+ Add') }}
                            </button>
                        </form>
                    </div>
                @endforeach
            </div>
        </section>
    @endif
</x-customer-layout>
