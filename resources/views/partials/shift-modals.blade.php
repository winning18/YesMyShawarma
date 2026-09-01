{{--
    Start/end shift modals — the markup half of shiftWidget() (see
    partials/shift-widget-script.blade.php). Included once, inside
    dashboard/_channel-header.blade.php's x-data="shiftWidget(...)" scope —
    the only shift toggle in the app (Orders/POS/History pages).
--}}

{{-- Start shift modal — always dismissable by clicking through, except
     when forceStart is true (staff, no active shift yet): no backdrop
     click, no close button, until they actually start one. --}}
<div x-show="startModalOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
    <div class="fixed inset-0 bg-black/50" @click="closeStartModal()"></div>

    <div class="relative bg-white rounded-lg shadow-lg max-w-sm w-full p-6">
        <h3 class="font-semibold text-gray-800 mb-1">{{ __('Start your shift') }}</h3>
        <p class="text-sm text-gray-500 mb-4" x-show="forceStart">
            {{ __('Starting a shift is required before you can use the dashboard.') }}
        </p>

        <div x-show="error" x-cloak class="bg-red-50 border border-red-200 text-red-700 text-sm rounded-lg p-3 mb-3" x-text="error"></div>

        <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('Starting cash (GHS), optional') }}</label>
        <input type="number" step="0.01" min="0" x-model="startingCash" class="w-full rounded-md border-gray-300 text-sm" placeholder="0.00">
        <p class="text-xs text-gray-400 mb-4">{{ __('For making change. This is not counted as part of today\'s sales.') }}</p>

        <div class="flex gap-2 justify-end">
            <button
                type="button" @click="closeStartModal()" x-show="!forceStart"
                class="px-4 py-2 border border-gray-300 rounded-md text-sm text-gray-700 hover:bg-gray-50"
            >{{ __('Cancel') }}</button>
            <button
                type="button" @click="confirmStart()"
                class="px-4 py-2 bg-gray-800 text-white text-sm font-semibold rounded-md hover:bg-gray-900"
            >{{ __('Start shift') }}</button>
        </div>
    </div>
</div>

{{-- End shift modal — total sales is required for staff
     (requireTotalSalesOnEnd), optional for manager/owner. --}}
<div x-show="endModalOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
    <div class="fixed inset-0 bg-black/50" @click="closeEndModal()"></div>

    <div class="relative bg-white rounded-lg shadow-lg max-w-sm w-full p-6">
        <h3 class="font-semibold text-gray-800 mb-4">{{ __('End your shift') }}</h3>

        <div x-show="error" x-cloak class="bg-red-50 border border-red-200 text-red-700 text-sm rounded-lg p-3 mb-3" x-text="error"></div>

        <p class="text-sm text-gray-600 mb-3" x-show="requireTotalSalesOnEnd && systemSales !== null">
            {{ __("Today's recorded sales:") }} <span class="font-semibold" x-text="formatMoney(systemSales)"></span>
        </p>

        <label class="block text-xs font-medium text-gray-500 mb-1">
            <span x-text="requireTotalSalesOnEnd ? '{{ __('Total sales (GHS)') }}' : '{{ __('Total sales (GHS), optional') }}'"></span>
        </label>
        <input
            type="number" step="0.01" min="0" x-model="totalSales"
            :required="requireTotalSalesOnEnd"
            class="w-full rounded-md border-gray-300 text-sm" placeholder="0.00"
        >
        <p class="text-xs text-gray-400 mb-4">
            {{ __('Total sales for the shift, separate from any starting cash you entered.') }}
            <span x-show="requireTotalSalesOnEnd"> {{ __("Can't be less than today's recorded sales. Entering more is fine and gets noted in the Today report.") }}</span>
        </p>

        <div class="flex gap-2 justify-end">
            <button
                type="button" @click="closeEndModal()"
                class="px-4 py-2 border border-gray-300 rounded-md text-sm text-gray-700 hover:bg-gray-50"
            >{{ __('Cancel') }}</button>
            <button
                type="button"
                @click="
                    if (requireTotalSalesOnEnd && !totalSales) { error = @js(__('Total sales is required to end your shift.')); }
                    else if (requireTotalSalesOnEnd && systemSales !== null && Math.round(parseFloat(totalSales) * 100) < systemSales) { error = @js(__('Total sales cannot be less than today\'s recorded sales.')); }
                    else { confirmEnd(); }
                "
                class="px-4 py-2 bg-gray-800 text-white text-sm font-semibold rounded-md hover:bg-gray-900"
            >{{ __('End shift') }}</button>
        </div>
    </div>
</div>
