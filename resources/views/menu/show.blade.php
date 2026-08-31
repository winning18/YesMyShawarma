<x-customer-layout
    title="{{ $item->name }} · {{ config('app.name') }}"
    :og-image="$item->imageUrl()"
    :og-description="$item->description ? Str::limit($item->description, 160) : null"
>
    <script type="application/ld+json">{!! json_encode($productSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>

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

            <div x-data="menuItemForm()">
                <form method="POST" action="{{ route('cart.add') }}" @submit="submit($event)">
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
                                <fieldset
                                    data-min-select="{{ $optionGroup->min_select }}"
                                    data-max-select="{{ $optionGroup->max_select }}"
                                    data-required="{{ $optionGroup->is_required ? '1' : '0' }}"
                                    data-group-name="{{ $optionGroup->name }}"
                                    @if ($optionGroup->max_select === 1) x-data="{ selected: null }" @endif
                                >
                                    <legend class="text-sm text-brand-black font-semibold mb-1.5">
                                        {{ $optionGroup->name }}@if ($optionGroup->is_required)<span class="text-brand-red"> *</span>@endif
                                    </legend>
                                    <div class="grid grid-cols-2 gap-x-3 gap-y-1.5">
                                        @foreach ($optionGroup->options as $option)
                                            <label class="flex items-center gap-1.5 text-sm">
                                                @if ($optionGroup->max_select === 1)
                                                    <input
                                                        type="checkbox" name="option_ids[]" value="{{ $option->id }}"
                                                        :checked="selected === {{ $option->id }}"
                                                        @change="selected = $event.target.checked ? {{ $option->id }} : null"
                                                        class="checkbox-check-black shrink-0 rounded border-2 border-brand-black text-brand-yellow focus:ring-brand-black"
                                                    >
                                                @else
                                                    <input type="checkbox" name="option_ids[]" value="{{ $option->id }}" class="checkbox-check-black shrink-0 rounded border-2 border-brand-black text-brand-yellow focus:ring-brand-black">
                                                @endif
                                                <span>{{ $option->name }} (+GH₵{{ number_format($option->price_delta / 100, 2) }})</span>
                                            </label>
                                        @endforeach
                                    </div>
                                </fieldset>
                            @endforeach
                        </div>
                    @endif

                    <div class="flex items-center gap-3 mb-6">
                        <label class="text-sm font-medium text-brand-black">{{ __('Qty') }}</label>
                        <input type="number" name="quantity" value="1" min="1" max="{{ \App\Services\Cart\CartService::MAX_LINE_QUANTITY }}" class="w-20 rounded-md border-2 border-brand-black focus:border-brand-black focus:ring-brand-black">
                    </div>

                    <button type="submit" class="w-full sm:w-auto px-8 py-3 bg-brand-black text-brand-yellow font-bold rounded-md hover:bg-brand-gray-700">
                        {{ __('Add to cart') }}
                    </button>
                </form>

                <x-menu-item-error-modal />
            </div>
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

    @include('partials.menu-item-form-script')
</x-customer-layout>
