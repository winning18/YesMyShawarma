<x-customer-layout :title="'Track order '.$order->reference.' · '.config('app.name')">
    @if ($branchWasClosed)
        <div class="max-w-5xl mx-auto mb-6 rounded-lg bg-brand-yellow-light border border-brand-yellow text-brand-black text-sm px-4 py-3">
            <p class="font-semibold">{{ __("We're currently closed.") }}</p>
            <p>
                @if ($nextOpeningLabel)
                    {{ __('This order will be prepared when we reopen :time.', ['time' => $nextOpeningLabel]) }}
                @else
                    {{ __('This order will be prepared at our next opening.') }}
                @endif
            </p>
        </div>
    @endif

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

                <div x-show="order.customer">
                    <p class="font-semibold" x-text="'{{ __('Thank you,') }} ' + (order.customer?.name ?? '{{ __('there') }}') + '!'"></p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                    <div class="md:col-span-2">
                        <template x-if="isProblemStatus(order.status)">
                            <div class="rounded-lg border-2 border-brand-red bg-brand-red-light p-4">
                                <p class="font-semibold text-brand-red-dark" x-text="statusLabel(order.status)"></p>
                                <p x-show="order.cancellation_reason" class="text-sm text-brand-black mt-1" x-text="order.cancellation_reason"></p>
                            </div>
                        </template>

                        <template x-if="!isProblemStatus(order.status)">
                            <ol class="relative ml-3 border-l-2 border-brand-gray-100 space-y-6">
                                <template x-for="(step, index) in steps" :key="step.label">
                                    <li class="relative pl-6">
                                        <span
                                            class="absolute -left-[9px] top-0.5 w-4 h-4 rounded-full ring-4 ring-brand-white"
                                            :class="stepReached(index) ? 'bg-brand-yellow' : 'bg-brand-gray-100'"
                                        ></span>
                                        <p
                                            class="text-sm"
                                            :class="stepReached(index) ? 'font-semibold text-brand-black' : 'text-brand-gray-300'"
                                            x-text="step.label"
                                        ></p>
                                        <p x-show="stepTime(index)" class="text-xs text-brand-gray-500 mt-0.5" x-text="stepTime(index)"></p>
                                    </li>
                                </template>
                            </ol>
                        </template>
                    </div>

                    <div class="space-y-6">
                        <h2 class="font-semibold">{{ __('Order summary') }}</h2>
                        <p class="text-sm text-brand-gray-500" x-text="order.branch?.name"></p>

                        <div x-show="order.fulfilment_type === 'delivery' && order.rider" x-cloak class="border border-brand-gray-100 rounded-lg p-4">
                            <p class="text-sm text-brand-gray-500 mb-1">{{ __('Your rider') }}</p>
                            <p class="font-semibold" x-text="order.rider?.name"></p>
                            <a class="text-sm underline" x-show="order.rider?.phone" :href="'tel:' + order.rider?.phone" x-text="order.rider?.phone"></a>
                        </div>

                        <div>
                            <p class="text-sm text-brand-gray-500 mb-2">{{ __('Items') }}</p>
                            <ul class="text-sm space-y-3">
                                <template x-for="item in order.items" :key="item.name">
                                    <li>
                                        <div class="flex items-center gap-3">
                                            <img
                                                x-show="item.image_url" :src="item.image_url" :alt="item.name"
                                                class="w-12 h-12 aspect-square object-cover rounded-md bg-brand-gray-100 shrink-0"
                                            >
                                            <div x-show="!item.image_url" class="w-12 h-12 aspect-square rounded-md bg-brand-gray-100 shrink-0 flex items-center justify-center">
                                                <svg viewBox="0 0 24 24" fill="none" class="w-5 h-5 text-brand-gray-300">
                                                    <path
                                                        d="M4 7a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V7Z"
                                                        stroke="currentColor" stroke-width="1.5"
                                                    />
                                                    <circle cx="9" cy="10.5" r="1.5" stroke="currentColor" stroke-width="1.5" />
                                                    <path d="m5 16 4.5-4 3 2.5L16 11l3 3.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                                                </svg>
                                            </div>
                                            <div class="flex-1 flex justify-between">
                                                <span x-text="item.quantity + 'x ' + item.name"></span>
                                                <span x-text="formatMoney(item.line_total)"></span>
                                            </div>
                                        </div>
                                        <template x-for="option in item.options" :key="option.name">
                                            <div class="flex justify-between text-brand-gray-500 pl-[60px] text-xs mt-1">
                                                <span x-text="option.name"></span>
                                                <span x-text="'+' + formatMoney(option.price_delta)"></span>
                                            </div>
                                        </template>
                                    </li>
                                </template>
                            </ul>
                        </div>

                        <div class="border-t border-brand-gray-100 pt-3 text-sm space-y-1">
                            <div class="flex justify-between">
                                <span>{{ __('Subtotal') }}</span>
                                <span x-text="formatMoney(order.subtotal)"></span>
                            </div>
                            <div class="flex justify-between" x-show="order.discount_total > 0" x-cloak>
                                <span>{{ __('Discount') }}</span>
                                <span x-text="'-' + formatMoney(order.discount_total)"></span>
                            </div>
                            <div class="flex justify-between" x-show="order.fulfilment_type === 'delivery'" x-cloak>
                                <span>{{ __('Delivery fee') }}</span>
                                <span x-text="order.delivery_fee > 0 ? formatMoney(order.delivery_fee) : '{{ __('Calculated on arrival') }}'"></span>
                            </div>
                            <div class="flex justify-between font-semibold border-t border-brand-gray-100 pt-2 mt-1">
                                <span>{{ __('Total') }}</span>
                                <span x-text="formatMoney(order.total)"></span>
                            </div>
                        </div>

                        <div class="border-t border-brand-gray-100 pt-4 text-sm text-brand-gray-500 space-y-1">
                            <div class="flex justify-between">
                                <span>{{ __('Fulfilment') }}</span>
                                <span class="text-brand-black" x-text="order.fulfilment_type === 'delivery' ? '{{ __('Delivery') }}' : '{{ __('Pickup') }}'"></span>
                            </div>
                            <template x-if="order.fulfilment_type === 'delivery'">
                                <div class="space-y-1">
                                    <div class="flex justify-between" x-show="order.delivery_address?.area_name">
                                        <span>{{ __('Area') }}</span>
                                        <span class="text-brand-black" x-text="order.delivery_address?.area_name"></span>
                                    </div>
                                    <div class="flex justify-between" x-show="order.delivery_address?.landmark">
                                        <span>{{ __('Landmark') }}</span>
                                        <span class="text-brand-black" x-text="order.delivery_address?.landmark"></span>
                                    </div>
                                </div>
                            </template>
                            <div class="flex justify-between">
                                <span>{{ __('Payment method') }}</span>
                                <span class="text-brand-black" x-text="order.payment_method === 'paystack' ? '{{ __('Pay now (card / mobile money)') }}' : '{{ __('Cash on delivery / pickup') }}'"></span>
                            </div>
                            <div class="flex justify-between" x-show="order.customer?.phone">
                                <span>{{ __('Phone number') }}</span>
                                <span class="text-brand-black" x-text="order.customer?.phone"></span>
                            </div>
                        </div>

                        <div class="border-t border-brand-gray-100 pt-4 text-sm text-brand-gray-500" x-show="order.branch">
                            <p x-show="order.branch?.address" x-text="order.branch?.address"></p>
                            <a class="underline" :href="'tel:' + order.branch?.phone" x-text="order.branch?.phone"></a>
                        </div>
                    </div>
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
                //
                // Pickup orders skip the rider entirely (orders.md) — the
                // "dispatched" status means the customer collected it, so
                // it's the terminal step for pickup. "delivered" only ever
                // applies to a rider completing a drop-off, so it's omitted
                // from the pickup list rather than shown as an unreached step
                // that will never happen.
                buildSteps(fulfilmentType) {
                    const isPickup = fulfilmentType === 'pickup';

                    const steps = [
                        { status: null, label: '{{ __('Order created') }}', timeKey: 'placed_at' },
                        { status: 'accepted', label: '{{ __('Order accepted') }}', timeKey: 'accepted_at' },
                        { status: 'preparing', label: '{{ __('Order in preparation') }}', timeKey: null },
                        {
                            status: 'ready',
                            label: isPickup ? '{{ __('Ready for pickup') }}' : '{{ __('Awaiting rider for pickup') }}',
                            timeKey: 'ready_at',
                        },
                        {
                            status: 'dispatched',
                            label: isPickup ? '{{ __('Picked up') }}' : '{{ __('Order picked up by rider') }}',
                            timeKey: 'dispatched_at',
                        },
                    ];

                    if (!isPickup) {
                        steps.push({ status: 'delivered', label: '{{ __('Order delivered') }}', timeKey: 'delivered_at' });
                    }

                    return steps;
                },

                stepReached(index) {
                    const statusOrder = ['pending_payment', 'paid', 'accepted', 'preparing', 'ready', 'dispatched', 'delivered'];
                    const step = this.steps[index];

                    if (step.status === null) return true;

                    return statusOrder.indexOf(this.order.status) >= statusOrder.indexOf(step.status);
                },

                stepTime(index) {
                    const step = this.steps[index];

                    if (!step.timeKey || !this.stepReached(index)) return null;

                    return this.formatTime(this.order.timeline?.[step.timeKey]);
                },

                formatMoney(pesewas) {
                    return 'GH₵' + ((pesewas ?? 0) / 100).toFixed(2);
                },

                // Stored UTC, displayed Africa/Accra — see CLAUDE.md.
                formatTime(iso) {
                    if (!iso) return null;

                    return new Date(iso).toLocaleTimeString('en-GB', {
                        timeZone: 'Africa/Accra',
                        hour: '2-digit',
                        minute: '2-digit',
                    });
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
