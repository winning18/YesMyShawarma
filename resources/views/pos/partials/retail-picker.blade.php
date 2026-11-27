{{--
    The pharmacy and shop till's left side (R3, 2026-11-27): one box for a
    barcode scanner (it types the code and Enter) or a name or code typed by
    hand; matches below, each with its packs (RetailPosController).
--}}
<div class="space-y-4">
    <form @submit.prevent="scanOrPick()" class="space-y-1">
        <label for="pos-scan" class="block text-xs font-medium text-gray-500">{{ __('Scan a barcode, or type a name or product code') }}</label>
        <input id="pos-scan" x-ref="scan" x-model="query" @input.debounce.250ms="searchProducts()" type="search" autocomplete="off" autofocus
            class="w-full rounded-md text-base" :class="scanError ? 'border-red-600 ring-1 ring-red-600' : 'border-gray-300'">
        <p x-show="scanError" x-cloak class="text-sm text-red-600" x-text="scanError"></p>
        <p x-show="scanAdded" x-cloak class="text-sm text-green-700" x-text="scanAdded"></p>
        <p x-show="stockWarning" x-cloak class="text-sm text-amber-800 bg-amber-50 rounded px-2 py-1" x-text="stockWarning"></p>
    </form>

    <ul class="divide-y divide-gray-100 bg-white rounded-lg border border-gray-200" x-show="results.length" x-cloak>
        <template x-for="product in results" :key="product.id">
            <li class="p-3 flex flex-wrap items-center gap-3">
                <div class="flex-1 min-w-[10rem]">
                    <p class="text-sm font-semibold text-gray-800" x-text="product.name"></p>
                    <p class="text-xs text-gray-500"><span class="font-mono" x-text="product.sku"></span> · <span x-text="formatMoney(product.price) + (product.unit ? ' / ' + product.unit : '')"></span>
                        · <span :class="product.stock > 0 ? 'text-gray-500' : 'text-red-600 font-semibold'" x-text="@js(__('In stock:')) + ' ' + product.stock"></span></p>
                </div>
                <input type="number" min="1" x-model.number="product.qty" class="w-20 rounded-md border-gray-300 text-sm" aria-label="{{ __('Quantity') }}">
                <button type="button" @click="addProduct(product, null)" class="px-3 py-1.5 bg-gray-800 text-white text-xs font-semibold rounded-md hover:bg-gray-900"
                    x-text="product.packs.length ? (product.unit || @js(__('Single'))) : @js(__('Add'))"></button>
                <template x-for="pack in product.packs" :key="pack.id">
                    <button type="button" @click="addProduct(product, pack)" class="px-3 py-1.5 border border-gray-300 text-xs font-semibold rounded-md hover:bg-gray-50"
                        x-text="pack.name + ' ' + formatMoney(pack.price)"></button>
                </template>
            </li>
        </template>
    </ul>
    <p x-show="query.trim() && !results.length && !searching && !scanError" x-cloak class="text-sm text-gray-500">{{ __('No products match. Check the spelling, or scan the barcode.') }}</p>
</div>
