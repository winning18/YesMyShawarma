<x-rider-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Deliveries') }}</h2>
    </x-slot>

    <div
        class="py-12"
        x-data="riderDashboard({{ $branchId ?? 'null' }}, {{ auth()->id() }})"
        x-init="init()"
    >
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-8">

            <template x-if="error">
                <div class="bg-red-50 border border-red-200 text-red-700 text-sm rounded-lg p-4" x-text="error"></div>
            </template>

            <section>
                <h3 class="text-lg font-semibold text-gray-800 mb-3">{{ __('My deliveries') }}</h3>

                <div class="space-y-4">
                    <template x-for="order in mine" :key="order.id">
                        <div class="bg-white shadow-sm rounded-lg p-6">
                            <div class="flex justify-between items-start gap-4">
                                <div class="min-w-0">
                                    <p class="font-semibold text-gray-900" x-text="order.reference"></p>
                                    <div class="flex items-center gap-1.5 mt-1">
                                        <span class="text-sm text-gray-500" x-text="statusLabel(order.status)"></span>
                                        <span
                                            class="text-xs font-medium px-2 py-0.5 rounded-full"
                                            :class="order.payment_method === 'paystack' ? 'bg-green-50 text-green-700' : 'bg-amber-50 text-amber-700'"
                                            x-text="paymentLabel(order)"
                                        ></span>
                                    </div>

                                    <p class="text-sm text-gray-700 mt-2" x-text="order.customer_name || 'Customer'"></p>
                                    <a class="text-sm text-blue-600 underline" :href="'tel:' + order.customer_phone" x-text="order.customer_phone"></a>

                                    <p class="text-sm text-gray-700 mt-2" x-show="order.delivery_address?.area_name" x-text="order.delivery_address?.area_name"></p>
                                    <p class="text-sm text-gray-500" x-show="order.delivery_address?.landmark" x-text="order.delivery_address?.landmark"></p>

                                    {{--
                                        The customer's live location captured at checkout —
                                        rider-only (OrderResource never sends lat/lng to
                                        staff/managers, see CLAUDE.md's identity model).
                                        Deep-links straight into Google Maps turn-by-turn
                                        navigation rather than building any in-app map — the
                                        rider's phone already does this better. Origin is
                                        pinned to the branch (also on the order payload) so
                                        the route is always "branch -> customer" — the actual
                                        delivery journey — rather than wherever the rider's
                                        device happens to report them at the moment they tap
                                        it; Maps still switches to live turn-by-turn from
                                        their real position the moment they start navigating.
                                        Not every order has a customer location: geolocation
                                        can fail or be denied at checkout, so there's an
                                        explicit fallback rather than a dead link.
                                    --}}
                                    <template x-if="order.delivery_address?.lat && order.delivery_address?.lng">
                                        <a
                                            :href="'https://www.google.com/maps/dir/?api=1'
                                                + (order.branch?.lat && order.branch?.lng ? '&origin=' + order.branch.lat + ',' + order.branch.lng : '')
                                                + '&destination=' + order.delivery_address.lat + ',' + order.delivery_address.lng"
                                            target="_blank" rel="noopener"
                                            class="inline-flex items-center gap-1.5 mt-2 text-sm font-semibold text-blue-600 hover:text-blue-700 hover:underline"
                                        >
                                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" class="w-4 h-4 shrink-0">
                                                <path d="M12 21s7-6.5 7-11.5a7 7 0 1 0-14 0C5 14.5 12 21 12 21Z" stroke="currentColor" stroke-width="1.75" stroke-linejoin="round" />
                                                <circle cx="12" cy="9.5" r="2.5" stroke="currentColor" stroke-width="1.75" />
                                            </svg>
                                            {{ __('Get directions') }}
                                        </a>
                                    </template>
                                    <p
                                        class="text-xs text-amber-700 mt-2"
                                        x-show="!(order.delivery_address?.lat && order.delivery_address?.lng)"
                                    >
                                        {{ __("Customer didn't share a live location. Use the area/landmark above, or call them.") }}
                                    </p>

                                    <ul class="text-sm text-gray-700 list-disc list-inside mt-2 space-y-0.5">
                                        <template x-for="item in order.items" :key="item.name + item.quantity">
                                            <li x-text="item.quantity + 'x ' + item.name + (item.options.length ? ' (' + item.options.join(', ') + ')' : '')"></li>
                                        </template>
                                    </ul>
                                </div>
                                <div class="shrink-0 flex flex-col gap-2 items-end">
                                    <button
                                        type="button"
                                        class="px-4 py-2 bg-blue-600 text-white text-sm font-semibold rounded-md hover:bg-blue-700"
                                        x-show="nextAction(order.status)"
                                        @click="advancePrimary(order)"
                                        x-text="nextAction(order.status)?.label"
                                    ></button>
                                    <button
                                        type="button"
                                        class="px-4 py-2 bg-red-50 text-red-700 text-sm font-semibold rounded-md hover:bg-red-100"
                                        x-show="order.status === 'dispatched'"
                                        @click="if (confirm(@js(__('Mark this delivery as failed?')))) advance(order.id, 'failed')"
                                    >{{ __('Delivery failed') }}</button>
                                </div>
                            </div>
                        </div>
                    </template>

                    <p x-show="mine.length === 0" class="text-sm text-gray-500">{{ __("You don't have any deliveries right now.") }}</p>
                </div>
            </section>
        </div>
    </div>

    <script>
        function riderDashboard(branchId, riderId) {
            return {
                mine: [],
                error: null,

                init() {
                    this.fetchData();

                    // Assignment always targets this one rider — no
                    // branch-wide channel needed for that half (orders.md).
                    // Status changes on orders already assigned still come
                    // via the branch channel, same as the staff dashboard.
                    window.Echo.private(`App.Models.User.${riderId}`)
                        .listen('.OrderAssignedToRider', () => this.fetchData());

                    if (branchId) {
                        window.Echo.private(`branch.${branchId}.orders`)
                            .listen('.OrderStatusChanged', () => this.fetchData());
                    }
                },

                async fetchData() {
                    try {
                        const response = await fetch('{{ route('rider.orders.data') }}', {
                            headers: { Accept: 'application/json' },
                        });

                        if (!response.ok) throw new Error('Failed to load orders');

                        const { data } = await response.json();

                        this.mine = data;
                        this.error = null;
                    } catch (e) {
                        this.error = e.message;
                    }
                },

                async advance(orderId, to) {
                    try {
                        const response = await fetch(`/dashboard/orders/${orderId}/advance`, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                Accept: 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                            },
                            body: JSON.stringify({ to }),
                        });

                        if (!response.ok) {
                            const payload = await response.json().catch(() => null);
                            throw new Error(payload?.message || 'Action failed');
                        }

                        this.error = null;
                    } catch (e) {
                        this.error = e.message;
                    } finally {
                        this.fetchData();
                    }
                },

                nextAction(status) {
                    return {
                        ready: { to: 'dispatched', label: @js(__('Picked up from branch')) },
                        dispatched: { to: 'delivered', label: @js(__('Mark delivered')) },
                    }[status] ?? null;
                },

                // Cash-on-delivery is only reconciled here — this is the
                // customer's word made official, so it gets its own
                // confirmation rather than riding along with the ordinary
                // "picked up" / "delivered" taps.
                advancePrimary(order) {
                    const action = this.nextAction(order.status);
                    if (!action) return;

                    if (action.to === 'delivered' && order.payment_method === 'cash') {
                        const prompt = @js(__('Confirm you have collected')) + ' ' + this.formatMoney(order.total) + ' ' + @js(__('cash from the customer?'));
                        if (!confirm(prompt)) return;
                    }

                    this.advance(order.id, action.to);
                },

                statusLabel(status) {
                    return {
                        ready: @js(__('Assigned to you, head to the branch')),
                        dispatched: @js(__('Out for delivery')),
                    }[status] ?? status;
                },

                // A rider only ever sees delivery orders (pickup skips the
                // rider entirely — orders.md), so this is always the
                // "collect cash at the door" / "already settled online"
                // distinction, never the pickup wording.
                paymentLabel(order) {
                    if (order.payment_method === 'paystack') return @js(__('Paid via Paystack'));
                    if (order.payment_method === 'momo') return @js(__('Momo'));

                    return @js(__('Cash on delivery'));
                },

                formatMoney(pesewas) {
                    return 'GH₵' + ((pesewas ?? 0) / 100).toFixed(2);
                },
            };
        }
    </script>
</x-rider-layout>
