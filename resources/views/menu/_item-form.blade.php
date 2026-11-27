{{-- The item page's add-to-cart form (or its sold-out note), shared by every website theme. --}}
@if (! $isAvailable)
    <div class="mb-6 rounded-lg bg-brand-gray-100 px-4 py-3">
        <p class="font-semibold">{{ __('Sold out at :branch right now', ['branch' => $branch->name]) }}</p>
        <p class="text-sm text-brand-gray-500">{{ __('Check back soon, or see what else is on the menu.') }}</p>
        <a href="{{ route('menu.index') }}" class="inline-block mt-3 px-6 py-2.5 bg-brand-surface text-brand-highlight font-bold rounded-md hover:opacity-90">{{ __('Browse the menu') }}</a>
    </div>
{{-- A pharmacy's prescription-only medicine isn't sold online (R5). --}}
@elseif ($item->needsPrescription())
    <div class="mb-6 rounded-lg bg-brand-gray-100 px-4 py-3">
        <p class="font-semibold">{{ __('Prescription needed') }}</p>
        <p class="text-sm text-brand-gray-500">{{ __('This medicine is sold only against a prescription. Visit us with it, and our pharmacist will help.') }}</p>
    </div>
@else
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
                            {{ $optionGroup->name }}@if ($optionGroup->is_required)<span class="text-red-600"> *</span>@endif
                        </legend>
                        <div class="grid grid-cols-2 gap-x-3 gap-y-1.5">
                            @foreach ($optionGroup->options as $option)
                                @if ($optionGroup->max_select === 1)
                                    <label class="flex items-center gap-1.5 text-sm">
                                        <input
                                            type="checkbox" name="option_ids[]" value="{{ $option->id }}"
                                            :checked="selected === {{ $option->id }}"
                                            @change="selected = $event.target.checked ? {{ $option->id }} : null"
                                            class="checkbox-check-black shrink-0 rounded border-2 border-brand-black text-brand-yellow focus:ring-brand-black"
                                        >
                                        <span>{{ $option->name }} (+GH₵{{ number_format($option->price_delta / 100, 2) }})</span>
                                    </label>
                                @else
                                    {{--
                                        Only a multi-select group's option gets a
                                        quantity control — "extra cheese x2" makes
                                        sense, "2x Large" as a size doesn't. Quantity
                                        is fixed for the whole line, not multiplied
                                        by the item's own Qty field below (see
                                        MenuPricingService).
                                    --}}
                                    <div x-data="{ checked: false, qty: 1 }" class="flex items-center gap-1.5 text-sm">
                                        <label class="flex items-center gap-1.5 flex-1 min-w-0">
                                            <input
                                                type="checkbox" name="option_ids[]" value="{{ $option->id }}" x-model="checked"
                                                class="checkbox-check-black shrink-0 rounded border-2 border-brand-black text-brand-yellow focus:ring-brand-black"
                                            >
                                            <span class="truncate">{{ $option->name }} (+GH₵{{ number_format($option->price_delta / 100, 2) }})</span>
                                        </label>
                                        <input type="hidden" name="option_qty[{{ $option->id }}]" :value="qty">
                                        <div class="flex items-center gap-1 shrink-0" x-show="checked" x-cloak>
                                            <button type="button" @click="qty = Math.max(1, qty - 1)" class="w-5 h-5 flex items-center justify-center border border-brand-black rounded text-xs" aria-label="{{ __('Decrease quantity') }}">&minus;</button>
                                            <span class="w-4 text-center text-xs" x-text="qty"></span>
                                            <button type="button" @click="qty = Math.min({{ \App\Services\Menu\MenuPricingService::MAX_OPTION_QUANTITY }}, qty + 1)" class="w-5 h-5 flex items-center justify-center border border-brand-black rounded text-xs" aria-label="{{ __('Increase quantity') }}">+</button>
                                        </div>
                                    </div>
                                @endif
                            @endforeach
                        </div>
                    </fieldset>
                @endforeach
            </div>
        @endif

        <div class="flex items-center gap-3 mb-6">
            <label class="text-sm font-medium text-brand-black">{{ __('Qty') }}</label>
            <input type="number" name="quantity" value="1" min="1" max="{{ \App\Services\Cart\CartService::maxLineQuantity() }}" class="w-20 rounded-md border-2 border-brand-black focus:border-brand-black focus:ring-brand-black">
        </div>

        <button type="submit" class="w-full sm:w-auto px-8 py-3 bg-brand-surface text-brand-highlight font-bold rounded-md hover:opacity-90">
            {{ __('Add to cart') }}
        </button>
    </form>

    <x-menu-item-error-modal />
</div>
@endif
