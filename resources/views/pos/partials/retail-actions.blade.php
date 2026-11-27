{{-- The pharmacy and shop till's buttons above the sale (R3): hold, held sales, return. --}}
<div class="flex flex-wrap gap-2 mb-3">
    <button type="button" @click="holdSale()" :disabled="lines.length === 0" class="px-3 py-1.5 border border-gray-300 text-xs font-semibold rounded-md hover:bg-gray-50 disabled:opacity-40">{{ __('Hold sale') }}</button>
    <button type="button" @click="openHeld()" class="px-3 py-1.5 border border-gray-300 text-xs font-semibold rounded-md hover:bg-gray-50">{{ __('Held sales') }}</button>
    <button type="button" @click="openReturn()" class="px-3 py-1.5 border border-gray-300 text-xs font-semibold rounded-md hover:bg-gray-50">{{ __('Return') }}</button>
</div>
{{-- A pharmacy's restricted medicines (R5): approved by a manager before the sale is placed. --}}
<div x-show="pharmacy && pharmacy.needs_approval" x-cloak class="mb-3 rounded-md border px-3 py-2 text-sm"
    :class="pharmacy?.approved ? 'border-green-300 bg-green-50 text-green-800' : 'border-red-300 bg-red-50 text-red-800'">
    <template x-if="pharmacy?.approved">
        <p x-text="@js(__('Approved by')) + ' ' + pharmacy.approved_by + (pharmacy.needs_prescription ? ' · ' + @js(__('prescription recorded')) : '')"></p>
    </template>
    <template x-if="pharmacy && !pharmacy.approved">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <p x-text="pharmacy.needs_prescription ? @js(__('Prescription-only medicine: record the prescription and have a manager approve.')) : @js(__('Pharmacy medicine: a manager approves before the sale.'))"></p>
            <button type="button" @click="openCheck()" class="px-3 py-1.5 bg-gray-800 text-white text-xs font-semibold rounded-md">{{ __('Approve') }}</button>
        </div>
    </template>
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
