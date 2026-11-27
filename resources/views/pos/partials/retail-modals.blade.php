{{-- The pharmacy and shop till's windows (R3): a price change, held sales, a return. --}}
@php
    $approval = '<div class="grid grid-cols-2 gap-2">
        <div><label class="block text-xs font-medium text-gray-500">'.e(__('Manager\'s Staff ID')).'</label><input x-model="approval.staff_code" autocomplete="off" class="mt-1 w-full rounded-md border-gray-300 text-sm uppercase"></div>
        <div><label class="block text-xs font-medium text-gray-500">'.e(__('PIN')).'</label><input x-model="approval.pin" type="password" inputmode="numeric" maxlength="6" autocomplete="off" class="mt-1 w-full rounded-md border-gray-300 text-sm tracking-widest"></div>
    </div>';
@endphp

{{-- Change a line's price --}}
<div x-show="priceLine" x-cloak class="fixed inset-0 bg-black/40 flex items-center justify-center p-4 z-50" @keydown.escape.window="priceLine = null">
    <div class="bg-white rounded-lg shadow-xl w-full max-w-sm p-5 space-y-3" @click.outside="priceLine = null">
        <h3 class="font-semibold text-gray-800">{{ __('Change price') }}</h3>
        <p class="text-sm text-gray-600" x-text="priceLine?.name_snapshot"></p>
        <div>
            <label class="block text-xs font-medium text-gray-500">{{ __('New price for one (GH₵)') }}</label>
            <input x-model="newPrice" type="number" step="0.01" min="0" class="mt-1 w-full rounded-md border-gray-300 text-sm">
            <p class="text-xs text-gray-500 mt-1" x-show="priceLine?.list_price_snapshot !== null">
                <button type="button" class="underline" @click="newPrice = ''">{{ __('Put the normal price back') }}</button>
            </p>
        </div>
        {!! $approval !!}
        <p x-show="tillError" class="text-sm text-red-600" x-text="tillError"></p>
        <div class="flex justify-end gap-2">
            <button type="button" @click="priceLine = null" class="px-3 py-2 text-sm text-gray-600">{{ __('Cancel') }}</button>
            <button type="button" @click="savePrice()" :disabled="tillBusy" class="px-3 py-2 bg-gray-800 text-white text-sm font-semibold rounded-md disabled:opacity-50">{{ __('Approve') }}</button>
        </div>
    </div>
</div>

{{-- Held sales --}}
<div x-show="heldOpen" x-cloak class="fixed inset-0 bg-black/40 flex items-center justify-center p-4 z-50" @keydown.escape.window="heldOpen = false">
    <div class="bg-white rounded-lg shadow-xl w-full max-w-md p-5 space-y-3" @click.outside="heldOpen = false">
        <h3 class="font-semibold text-gray-800">{{ __('Held sales') }}</h3>
        <p x-show="!held.length" class="text-sm text-gray-500">{{ __('No sales are held. They\'re cleared when the till closes.') }}</p>
        <ul class="divide-y divide-gray-100">
            <template x-for="sale in held" :key="sale.id">
                <li class="py-2 flex items-center justify-between gap-2 text-sm">
                    <span><span class="font-semibold" x-text="sale.label || (sale.lines + ' ' + @js(__('items')))"></span> <span class="text-gray-500" x-text="sale.at + ' · ' + (sale.by || '')"></span></span>
                    <span class="flex gap-2">
                        <button type="button" @click="recallSale(sale)" class="px-2 py-1 bg-gray-800 text-white text-xs rounded-md">{{ __('Recall') }}</button>
                        <button type="button" @click="discardSale(sale)" class="text-xs text-red-600 hover:underline">{{ __('Delete') }}</button>
                    </span>
                </li>
            </template>
        </ul>
        <p x-show="tillError" class="text-sm text-red-600" x-text="tillError"></p>
        <div class="flex justify-end"><button type="button" @click="heldOpen = false" class="px-3 py-2 text-sm text-gray-600">{{ __('Close') }}</button></div>
    </div>
</div>

{{-- A return --}}
<div x-show="returnOpen" x-cloak class="fixed inset-0 bg-black/40 flex items-center justify-center p-4 z-50" @keydown.escape.window="returnOpen = false">
    <div class="bg-white rounded-lg shadow-xl w-full max-w-lg p-5 space-y-3 max-h-[90vh] overflow-y-auto" @click.outside="returnOpen = false">
        <h3 class="font-semibold text-gray-800">{{ __('Return') }}</h3>
        <form @submit.prevent="findSale()" class="flex gap-2">
            <input x-model="returnReference" x-init="$watch('returnOpen', (open) => open && $nextTick(() => $el.focus()))" placeholder="{{ __('Receipt number, e.g. :example', ['example' => app(\App\Services\Settings\SettingsService::class)->prefix(\App\Services\Settings\SettingsService::ORDER_REFERENCE_PREFIX_POS).'-7K3QP9']) }}" class="flex-1 rounded-md border-gray-300 text-sm uppercase" autocomplete="off">
            <button class="px-3 py-2 bg-gray-800 text-white text-sm font-semibold rounded-md">{{ __('Find') }}</button>
        </form>
        <template x-if="returnSale">
            <div class="space-y-3">
                <p class="text-sm text-gray-600" x-text="returnSale.order.reference + ' · ' + returnSale.order.placed_at + ' · ' + (returnSale.order.payment_method === 'cash' ? @js(__('paid in cash')) : @js(__('paid by MoMo')))"></p>
                <ul class="divide-y divide-gray-100 text-sm">
                    <template x-for="line in returnSale.lines" :key="line.id">
                        <li class="py-2 flex items-center justify-between gap-2">
                            <span><span x-text="line.name"></span> <span class="text-gray-500" x-text="'(' + line.returnable + ' ' + @js(__('of')) + ' ' + line.quantity + ' ' + @js(__('can come back')) + ')'"></span></span>
                            <input type="number" min="0" :max="line.returnable" x-model.number="returnQuantities[line.id]" :disabled="line.returnable === 0" class="w-20 rounded-md border-gray-300 text-sm">
                        </li>
                    </template>
                </ul>
                <p class="text-sm font-semibold" x-text="@js(__('To give back:')) + ' ' + formatMoney(returnTotal())"></p>
                <div>
                    <label class="block text-xs font-medium text-gray-500">{{ __('Why it came back') }}</label>
                    <input x-model="returnReason" maxlength="255" placeholder="{{ __('e.g. Wrong size, Damaged, Exchanged') }}" class="mt-1 w-full rounded-md border-gray-300 text-sm">
                </div>
                {!! $approval !!}
            </div>
        </template>
        <p x-show="tillError" class="text-sm text-red-600" x-text="tillError"></p>
        <div class="flex justify-end gap-2">
            <button type="button" @click="returnOpen = false" class="px-3 py-2 text-sm text-gray-600">{{ __('Cancel') }}</button>
            <button type="button" x-show="returnSale" @click="saveReturn()" :disabled="tillBusy || returnTotal() === 0" class="px-3 py-2 bg-gray-800 text-white text-sm font-semibold rounded-md disabled:opacity-50">{{ __('Approve return') }}</button>
        </div>
    </div>
</div>
