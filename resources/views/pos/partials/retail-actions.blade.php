{{-- The pharmacy and shop till's buttons above the sale (R3): hold, held sales, return. --}}
<div class="flex flex-wrap gap-2 mb-3">
    <button type="button" @click="holdSale()" :disabled="lines.length === 0" class="px-3 py-1.5 border border-gray-300 text-xs font-semibold rounded-md hover:bg-gray-50 disabled:opacity-40">{{ __('Hold sale') }}</button>
    <button type="button" @click="openHeld()" class="px-3 py-1.5 border border-gray-300 text-xs font-semibold rounded-md hover:bg-gray-50">{{ __('Held sales') }}</button>
    <button type="button" @click="openReturn()" class="px-3 py-1.5 border border-gray-300 text-xs font-semibold rounded-md hover:bg-gray-50">{{ __('Return') }}</button>
</div>
<p x-show="tillMessage" x-cloak class="mb-3 text-sm text-green-700 bg-green-50 rounded-md px-3 py-2" x-text="tillMessage"></p>
{{-- An exchange: what came back, set against the new sale. --}}
<div x-show="returnCredit > 0" x-cloak class="mb-3 rounded-md border border-amber-300 bg-amber-50 px-3 py-2 text-sm">
    <p><span class="font-semibold">{{ __('Returned') }}</span> <span x-text="formatMoney(returnCredit)"></span></p>
    <p x-show="lines.length" x-text="(subtotal - displayedDiscount() + tax().added) >= returnCredit
        ? @js(__('Exchange: the customer pays')) + ' ' + formatMoney(subtotal - displayedDiscount() + tax().added - returnCredit) + ' ' + @js(__('more (record the full sale, then give back the return).'))
        : @js(__('Exchange: give the customer back')) + ' ' + formatMoney(returnCredit - (subtotal - displayedDiscount() + tax().added)) + '.'"></p>
    <button type="button" @click="returnCredit = 0" class="text-xs underline text-gray-600">{{ __('Done') }}</button>
</div>
