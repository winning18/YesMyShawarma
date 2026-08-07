<div class="flex justify-between items-start mb-3">
    <a href="{{ route('menu.show', $item) }}" class="font-semibold text-brand-yellow hover:underline">{{ $item->name }}</a>
    <p class="font-semibold whitespace-nowrap ml-2 text-brand-yellow">
        GH₵{{ number_format($item->base_price / 100, 2) }}
    </p>
</div>

<form method="POST" action="{{ route('cart.add') }}" class="space-y-3">
    @csrf
    <input type="hidden" name="branch_id" value="{{ $branch->id }}">
    <input type="hidden" name="menu_item_id" value="{{ $item->id }}">

    @foreach ($item->optionGroups as $optionGroup)
        <fieldset class="text-sm">
            <legend class="text-brand-white mb-1">{{ $optionGroup->name }}</legend>
            <div class="grid grid-cols-2 gap-x-3 gap-y-1.5">
                @foreach ($optionGroup->options as $option)
                    <label class="flex items-center gap-1.5 text-brand-white">
                        <input type="checkbox" name="option_ids[]" value="{{ $option->id }}" class="checkbox-check-black shrink-0 rounded border-brand-black text-brand-yellow focus:ring-brand-black">
                        <span>{{ $option->name }} (+GH₵{{ number_format($option->price_delta / 100, 2) }})</span>
                    </label>
                @endforeach
            </div>
        </fieldset>
    @endforeach

    <div class="flex items-center justify-between">
        <div class="flex items-center gap-3">
            <label class="text-sm text-brand-white">{{ __('Qty') }}</label>
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
