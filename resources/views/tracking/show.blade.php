<x-customer-layout :title="'Track order '.$order->reference.' · '.config('app.name')">
    <div
        x-data="orderTracker(@js($order->track_token), '{{ route('tracking.data', $order) }}')"
        x-init="init()"
    >
        <template x-if="!loaded">
            <p class="text-brand-gray-500">{{ __('Loading…') }}</p>
        </template>

        <template x-if="loaded">
            <div class="space-y-8">
                <div>
                    <p class="text-sm text-brand-gray-500">{{ __('Order') }}</p>
                    <h1 class="text-2xl font-bold" x-text="order.reference"></h1>
                </div>

                <template x-if="isProblemStatus(order.status)">
                    <div class="rounded-lg border-2 border-brand-red bg-brand-red-light p-4">
                        <p class="font-semibold text-brand-red-dark" x-text="statusLabel(order.status)"></p>
                        <p x-show="order.cancellation_reason" class="text-sm text-brand-black mt-1" x-text="order.cancellation_reason"></p>
                    </div>
                </template>

                <template x-if="!isProblemStatus(order.status)">
                    <ol class="space-y-3">
                        <template x-for="(step, index) in steps" :key="step.key">
                            <li class="flex items-center gap-3">
                                <span
                                    class="w-6 h-6 rounded-full flex items-center justify-center text-xs font-bold shrink-0"
                                    :class="stepReached(index) ? 'bg-brand-yellow text-brand-black' : 'bg-brand-gray-100 text-brand-gray-300'"
                                >
                                    <span x-show="stepReached(index)">&#10003;</span>
                                </span>
                                <span :class="stepReached(index) ? 'text-brand-black font-medium' : 'text-brand-gray-300'" x-text="step.label"></span>
                            </li>
                        </template>
                    </ol>
                </template>

                <div class="border-t border-brand-gray-100 pt-6">
                    <p class="text-sm text-brand-gray-500 mb-2">{{ __('Items') }}</p>
                    <ul class="text-sm space-y-1">
                        <template x-for="item in order.items" :key="item.name">
                            <li x-text="item.quantity + 'x ' + item.name"></li>
                        </template>
                    </ul>
                </div>

                <div class="border-t border-brand-gray-100 pt-6 text-sm text-brand-gray-500" x-show="order.branch">
                    <p x-text="order.branch?.name"></p>
                    <p x-show="order.branch?.address" x-text="order.branch?.address"></p>
                    <a class="underline" :href="'tel:' + order.branch?.phone" x-text="order.branch?.phone"></a>
                </div>
            </div>
        </template>
    </div>

    <script>
        function orderTracker(trackToken, dataUrl) {
            return {
                loaded: false,
                order: null,
                steps: [],

                init() {
                    this.fetchData();

                    window.Echo.channel(`order.${trackToken}`)
                        .listen('.OrderStatusChanged', () => this.fetchData());
                },

                async fetchData() {
                    const response = await fetch(dataUrl, { headers: { Accept: 'application/json' } });
                    const { data } = await response.json();
                    this.order = data;
                    this.steps = this.buildSteps(data.fulfilment_type);
                    this.loaded = true;
                },

                // `status` is null for the first step (reaching it just means
                // the order exists) — every other step maps to the order
                // status that must have been reached at least once. There's
                // no dedicated "preparing_at" column (see OrderStateMachine's
                // TIMESTAMP_COLUMNS), so this compares status order rather
                // than checking per-step timestamps, which works uniformly
                // for every step including that one.
                buildSteps(fulfilmentType) {
                    const isPickup = fulfilmentType === 'pickup';

                    return [
                        { status: null, label: '{{ __('Order placed') }}' },
                        { status: 'accepted', label: '{{ __('Accepted') }}' },
                        { status: 'preparing', label: '{{ __('Preparing') }}' },
                        { status: 'ready', label: isPickup ? '{{ __('Ready for pickup') }}' : '{{ __('Ready') }}' },
                        { status: 'dispatched', label: isPickup ? '{{ __('Collected') }}' : '{{ __('Out for delivery') }}' },
                        { status: 'delivered', label: '{{ __('Complete') }}' },
                    ];
                },

                stepReached(index) {
                    const statusOrder = ['pending_payment', 'paid', 'accepted', 'preparing', 'ready', 'dispatched', 'delivered'];
                    const step = this.steps[index];

                    if (step.status === null) return true;

                    return statusOrder.indexOf(this.order.status) >= statusOrder.indexOf(step.status);
                },

                isProblemStatus(status) {
                    return ['rejected', 'cancelled', 'failed', 'abandoned', 'refunded'].includes(status);
                },

                statusLabel(status) {
                    return {
                        rejected: '{{ __('This order was declined by the branch.') }}',
                        cancelled: '{{ __('This order was cancelled.') }}',
                        failed: '{{ __('Delivery could not be completed.') }}',
                        abandoned: '{{ __('This order was not paid in time and was abandoned.') }}',
                        refunded: '{{ __('This order was refunded.') }}',
                    }[status] ?? status;
                },
            };
        }
    </script>
</x-customer-layout>
