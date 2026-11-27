{{-- On a pharmacy's or shop's sale line (R3): a typed quantity, and the price changed with a manager's approval. --}}
<div class="flex items-center gap-2 mt-1 text-xs" x-show="!line.is_free">
    <input type="number" min="1" :value="line.quantity" @change="changeQuantity(line.line_id, parseInt($event.target.value) || 0)" class="w-16 rounded border-gray-300 text-xs py-0.5" aria-label="{{ __('Quantity') }}">
    <span class="text-gray-500" x-text="'× ' + formatMoney(line.unit_price_snapshot)"></span>
    <span x-show="line.list_price_snapshot !== null" class="text-gray-400 line-through" x-text="formatMoney(line.list_price_snapshot)"></span>
    <button type="button" @click="openPrice(line)" class="text-indigo-600 hover:underline">{{ __('Change price') }}</button>
</div>
