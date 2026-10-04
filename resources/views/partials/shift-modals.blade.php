{{--
    Start/end shift modals — the markup half of shiftWidget() (see
    partials/shift-widget-script.blade.php). Included once, inside
    dashboard/_channel-header.blade.php's x-data="shiftWidget(...)" scope —
    the only shift toggle in the app (Orders/POS/History pages).
--}}

{{-- Start shift modal — always dismissable by clicking through, except
     when forceStart is true (staff, no active shift yet): no backdrop
     click, no close button, until they actually start one — a "Log out"
     button takes its place instead, so someone who isn't ready to start a
     shift right now isn't trapped on this screen with no way out but to
     start one anyway. --}}
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
            {{--
                Starting a shift is only ever required to actually process
                orders (Dashboard) or ring up a sale (POS) — every other
                feature (Order History, Damage Reports, Refunds, Reports)
                works with no shift at all, so "not right now" has a real
                destination rather than just logging out.
            --}}
            <a
                href="{{ route('dashboard.orders.history') }}" x-show="forceStart"
                class="px-4 py-2 border border-gray-300 rounded-md text-sm text-gray-700 hover:bg-gray-50 inline-flex items-center"
            >{{ __('Skip for now') }}</a>
            <form method="POST" action="{{ route('logout') }}" x-show="forceStart">
                @csrf
                <button
                    type="submit"
                    class="px-4 py-2 border border-gray-300 rounded-md text-sm text-gray-700 hover:bg-gray-50"
                >{{ __('Log out') }}</button>
            </form>
            <button
                type="button" @click="confirmStart()"
                class="px-4 py-2 bg-gray-800 text-white text-sm font-semibold rounded-md hover:bg-gray-900"
            >{{ __('Start shift') }}</button>
        </div>
    </div>
</div>

{{-- End shift modal — total sales and expenses (or an explicit "none
     today") are required of whoever ends a shift here; see
     shift-widget-script.blade.php's confirmEnd() for the actual
     validation, this is just the markup. --}}
<div x-show="endModalOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
    <div class="fixed inset-0 bg-black/50" @click="closeEndModal()"></div>

    <div class="relative bg-white rounded-lg shadow-lg max-w-sm w-full p-6 max-h-[90vh] overflow-y-auto">
        <h3 class="font-semibold text-gray-800 mb-4">{{ __('End your shift') }}</h3>

        <div x-show="error" x-cloak class="bg-red-50 border border-red-200 text-red-700 text-sm rounded-lg p-3 mb-3" x-text="error"></div>

        <p class="text-sm text-gray-600 mb-3" x-show="systemSales !== null">
            {{ __("This shift's recorded sales:") }} <span class="font-semibold" x-text="formatMoney(systemSales)"></span>
        </p>

        <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('Total sales (GHS)') }}</label>
        <input
            type="number" step="0.01" min="0" x-model="totalSales" required
            class="w-full rounded-md border-gray-300 text-sm" placeholder="0.00"
        >
        <p class="text-xs text-gray-400 mb-4">
            {{ __('Total sales for the shift, separate from any starting cash you entered.') }}
            {{ __("Can't be less than this shift's recorded sales. Entering more is fine and gets noted in the Today report.") }}
        </p>

        <div class="border-t border-gray-100 pt-3 mb-4">
            <div class="flex items-center justify-between mb-2">
                <label class="block text-xs font-medium text-gray-500">{{ __('Expenses') }}</label>
                <label class="flex items-center gap-1.5 text-xs text-gray-600">
                    <input type="checkbox" x-model="noExpenses" @change="if (noExpenses) expenses = []" class="rounded border-gray-300">
                    {{ __('No expenses today') }}
                </label>
            </div>

            <template x-if="!noExpenses">
                <div class="space-y-2">
                    <template x-for="(expense, index) in expenses" :key="index">
                        <div class="flex gap-2 items-start">
                            <input
                                type="text" x-model="expense.description"
                                placeholder="{{ __('Description') }}"
                                class="flex-1 min-w-0 rounded-md border-gray-300 text-sm"
                            >
                            <input
                                type="number" step="0.01" min="0.01" x-model="expense.amount"
                                placeholder="0.00"
                                class="w-24 rounded-md border-gray-300 text-sm"
                            >
                            <button
                                type="button" @click="removeExpenseRow(index)"
                                class="shrink-0 text-gray-400 hover:text-red-600 px-1" aria-label="{{ __('Remove') }}"
                            >&times;</button>
                        </div>
                    </template>

                    <button
                        type="button" @click="addExpenseRow()"
                        class="text-xs text-blue-600 hover:underline"
                    >+ {{ __('Add expense') }}</button>
                </div>
            </template>

            <p class="text-xs text-gray-400 mt-2">
                {{ __('Each expense is deducted from total sales in the Today report.') }}
            </p>
        </div>

        <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('Notes (optional)') }}</label>
        <textarea
            x-model="closingNote" rows="2" maxlength="255"
            class="w-full rounded-md border-gray-300 text-sm mb-4"
            placeholder="{{ __('Anything worth flagging about today\'s shift...') }}"
        ></textarea>

        <div class="flex gap-2 justify-end">
            <button
                type="button" @click="closeEndModal()"
                class="px-4 py-2 border border-gray-300 rounded-md text-sm text-gray-700 hover:bg-gray-50"
            >{{ __('Cancel') }}</button>
            <button
                type="button" @click="confirmEnd()"
                class="px-4 py-2 bg-gray-800 text-white text-sm font-semibold rounded-md hover:bg-gray-900"
            >{{ __('End shift') }}</button>
        </div>
    </div>
</div>
