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
                        <label class="flex items-center gap-1.5 text-brand-white">
                            @if ($optionGroup->max_select === 1)
                                <input
                                    type="checkbox" name="option_ids[]" value="{{ $option->id }}"
                                    :checked="selected === {{ $option->id }}"
                                    @change="selected = $event.target.checked ? {{ $option->id }} : null"
                                    class="checkbox-check-black shrink-0 rounded border-brand-black text-brand-yellow focus:ring-brand-black"
                                >
                            @else
                                <input type="checkbox" name="option_ids[]" value="{{ $option->id }}" class="checkbox-check-black shrink-0 rounded border-brand-black text-brand-yellow focus:ring-brand-black">
                            @endif
                            <span>{{ $option->name }} (+GH₵{{ number_format($option->price_delta / 100, 2) }})</span>
                        </label>
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
