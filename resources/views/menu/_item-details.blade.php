<div class="flex justify-between items-start mb-3">
    <a href="{{ route('menu.show', $item) }}" class="font-semibold text-brand-white hover:underline">{{ $item->name }}</a>
    <p class="font-semibold whitespace-nowrap ml-2 text-brand-white">
        GH₵{{ number_format($item->base_price / 100, 2) }}
    </p>
</div>

<div x-data="menuItemForm()">
    <form method="POST" action="{{ route('cart.add') }}" class="space-y-3" @submit="submit($event)">
        @csrf
        <input type="hidden" name="branch_id" value="{{ $branch->id }}">
        <input type="hidden" name="menu_item_id" value="{{ $item->id }}">

        @foreach ($item->optionGroups as $optionGroup)
            <fieldset
                class="text-sm"
                data-min-select="{{ $optionGroup->min_select }}"
                data-max-select="{{ $optionGroup->max_select }}"
                data-required="{{ $optionGroup->is_required ? '1' : '0' }}"
                data-group-name="{{ $optionGroup->name }}"
                @if ($optionGroup->max_select === 1) x-data="{ selected: null }" @endif
            >
                <legend class="text-brand-yellow font-semibold mb-1">
                    {{ $optionGroup->name }}@if ($optionGroup->is_required)<span class="text-brand-red"> *</span>@endif
                </legend>
                <div class="grid grid-cols-2 gap-x-3 gap-y-1.5">
                    @foreach ($optionGroup->options as $option)
                        @if ($optionGroup->max_select === 1)
                            <label class="flex items-center gap-1.5 text-brand-white">
                                <input
                                    type="checkbox" name="option_ids[]" value="{{ $option->id }}"
                                    :checked="selected === {{ $option->id }}"
                                    @change="selected = $event.target.checked ? {{ $option->id }} : null"
                                    class="checkbox-check-black shrink-0 rounded border-brand-black text-brand-yellow focus:ring-brand-black"
                                >
                                <span>{{ $option->name }} (+GH₵{{ number_format($option->price_delta / 100, 2) }})</span>
                            </label>
                        @else
                            {{-- See menu/show.blade.php's equivalent block for why only a multi-select option gets this. --}}
                            <div x-data="{ checked: false, qty: 1 }" class="flex items-center gap-1.5 text-brand-white">
                                <label class="flex items-center gap-1.5 flex-1 min-w-0">
                                    <input
                                        type="checkbox" name="option_ids[]" value="{{ $option->id }}" x-model="checked"
                                        class="checkbox-check-black shrink-0 rounded border-brand-black text-brand-yellow focus:ring-brand-black"
                                    >
                                    <span class="truncate">{{ $option->name }} (+GH₵{{ number_format($option->price_delta / 100, 2) }})</span>
                                </label>
                                <input type="hidden" name="option_qty[{{ $option->id }}]" :value="qty">
                                <div class="flex items-center gap-1 shrink-0" x-show="checked" x-cloak>
                                    <button type="button" @click="qty = Math.max(1, qty - 1)" class="w-5 h-5 flex items-center justify-center border border-brand-gray-300 rounded text-xs" aria-label="{{ __('Decrease quantity') }}">&minus;</button>
                                    <span class="w-4 text-center text-xs" x-text="qty"></span>
                                    <button type="button" @click="qty = Math.min({{ \App\Services\Menu\MenuPricingService::MAX_OPTION_QUANTITY }}, qty + 1)" class="w-5 h-5 flex items-center justify-center border border-brand-gray-300 rounded text-xs" aria-label="{{ __('Increase quantity') }}">+</button>
                                </div>
                            </div>
                        @endif
                    @endforeach
                </div>
            </fieldset>
        @endforeach

        <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
                <label class="text-sm text-brand-yellow font-semibold">{{ __('Qty') }}</label>
                <input type="number" name="quantity" value="1" min="1" max="{{ \App\Services\Cart\CartService::MAX_LINE_QUANTITY }}" class="w-16 rounded-md border-brand-gray-300 text-sm focus:border-brand-yellow focus:ring-brand-yellow">
            </div>
            <button
                type="submit"
                class="px-4 py-2 bg-brand-yellow text-brand-black text-sm font-semibold rounded-md hover:bg-brand-yellow-dark"
            >
                {{ __('Add to cart') }}
            </button>
        </div>
    </form>

    <x-menu-item-error-modal />
</div>
