<x-rider-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Deliveries') }}</h2>
    </x-slot>

    <div
        class="py-12"
        x-data="riderDashboard({{ $branchId ?? 'null' }}, {{ auth()->id() }})"
        x-init="init()"
    >
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 space-y-8">

            {{--
                Two separate fields on purpose, not one shared "error" —
                actionError (a failed tap on Arrived/Picked up/Delivered/
                failed) must survive the very next background refresh,
                whether that's this same action's own fetchData() call or
                an unrelated realtime push a few seconds later; error
                (couldn't load the order list at all) is expected to
                self-clear the moment a refresh actually succeeds. Sharing
                one field meant a real action error was flashing and
                disappearing the instant the follow-up list refresh
                succeeded, before a rider had a chance to read it.
            --}}
            <template x-if="actionError">
                <div class="bg-red-50 border border-red-200 text-red-700 text-sm rounded-lg p-4 flex items-start justify-between gap-3">
                    <span x-text="actionError"></span>
                    <button type="button" @click="actionError = null" class="shrink-0 font-bold leading-none text-red-700 hover:text-red-900" aria-label="{{ __('Dismiss') }}">&times;</button>
                </div>
            </template>
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
                                            class="inline-flex items-center justify-center gap-1.5 mt-2 px-4 py-2 bg-red-600 text-white text-sm font-semibold rounded-md hover:bg-red-700"
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

                                    {{--
                                        Always visible here, not just sprung on the rider in a
                                        confirm dialog right before they tap "delivered" — this
                                        is the number that answers "how much do I collect", and
                                        it's the same figure whether it's a plain cash order or
                                        a paystack order whose delivery fee wasn't charged
                                        online (see OrderResource::cashToCollectPesewas()).
                                    --}}
                                    <p class="text-sm font-semibold text-gray-900 mt-2" x-show="order.cash_to_collect > 0" x-text="@js(__('Collect')) + ' ' + formatMoney(order.cash_to_collect) + ' ' + @js(__('cash'))"></p>
                                    <p class="text-sm text-green-700 mt-2" x-show="order.cash_to_collect === 0 && !feePending(order)">{{ __('Fully paid — nothing to collect') }}</p>
                                    {{--
                                        Customer didn't share a location at checkout, so
                                        delivery_fee is deliberately still 0 — it's only
                                        calculated once this rider taps "Arrived", from their
                                        own position at that moment (orders.md's "Delivery fee
                                        at arrival"). Told upfront, not just sprung on them
                                        as a surprise total once they tap delivered.
                                    --}}
                                    <p class="text-sm text-amber-700 mt-2" x-show="feePending(order)">
                                        {{ __("Customer didn't share a location — delivery fee will be added when you tap Arrived.") }}
                                    </p>

                                    <ul class="text-sm text-gray-700 list-disc list-inside mt-2 space-y-0.5">
                                        <template x-for="item in order.items" :key="item.name + item.quantity">
                                            <li x-text="item.quantity + 'x ' + item.name + (item.options.length ? ' (' + item.options.map(o => o.name + (o.quantity > 1 ? ' x' + o.quantity : '')).join(', ') + ')' : '')"></li>
                                        </template>
                                    </ul>
                                </div>
                                <div class="shrink-0 flex flex-col gap-2 items-end">
                                    <button
                                        type="button"
                                        class="px-4 py-2 bg-blue-600 text-white text-sm font-semibold rounded-md hover:bg-blue-700 disabled:opacity-50"
                                        x-show="nextAction(order)"
                                        :disabled="arriving === order.id"
                                        @click="advancePrimary(order)"
                                        x-text="arriving === order.id ? @js(__('Arriving…')) : nextAction(order)?.label"
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
                actionError: null,
                arriving: null,

                init() {
                    this.fetchData();

                    // Assignment always targets this one rider — no
                    // branch-wide channel needed for that half. Status
                    // changes on orders already assigned still come via
                    // the branch channel, same as the staff dashboard.
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
                    this.actionError = null;

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
                    } catch (e) {
                        this.actionError = e.message;
                    } finally {
                        this.fetchData();
                    }
                },

                // 'dispatched' splits into two steps now — arrive, then
                // deliver — so this needs the whole order, not just its
                // status, to tell which one comes next.
                nextAction(order) {
                    if (order.status === 'ready') {
                        return { type: 'advance', to: 'dispatched', label: @js(__('Picked up from branch')) };
                    }

                    if (order.status === 'dispatched' && !order.arrived_at) {
                        return { type: 'arrive', label: @js(__('Arrived')) };
                    }

                    if (order.status === 'dispatched' && order.arrived_at) {
                        return { type: 'advance', to: 'delivered', label: @js(__('Mark delivered')) };
                    }

                    return null;
                },

                // True only in the window before this rider has tapped
                // Arrived on an order whose customer never shared a
                // checkout location — delivery_fee sits at 0 deliberately
                // until then (see nextAction/arrive and orders.md's
                // "Delivery fee at arrival" section), so cash_to_collect
                // being 0 here doesn't yet mean "fully paid".
                feePending(order) {
                    return order.fulfilment_type === 'delivery'
                        && !order.delivery_address?.lat
                        && !order.arrived_at
                        && order.delivery_fee === 0;
                },

                // Any amount still owed in cash — not just a plain cash
                // order — is only reconciled here, so it gets its own
                // confirmation rather than riding along with the ordinary
                // "picked up" / "delivered" taps. A paystack order whose
                // delivery fee was never charged online still needs this:
                // cash_to_collect is what actually decides whether to ask,
                // not payment_method.
                advancePrimary(order) {
                    const action = this.nextAction(order);
                    if (!action) return;

                    if (action.type === 'arrive') {
                        this.arrive(order);
                        return;
                    }

                    if (action.to === 'delivered' && order.cash_to_collect > 0) {
                        const prompt = @js(__('Confirm you have collected')) + ' ' + this.formatMoney(order.cash_to_collect) + ' ' + @js(__('cash from the customer?'));
                        if (!confirm(prompt)) return;
                    }

                    this.advance(order.id, action.to);
                },

                // Geolocation here is best-effort, never blocking — a
                // rider whose GPS fails or who denies the prompt must
                // still be able to complete the delivery.
                // OrderArrivalService falls back to the flat minimum fee
                // server-side when lat/lng come through null.
                arrive(order) {
                    this.arriving = order.id;

                    const send = (lat, lng) => this.postArrival(order.id, lat, lng);

                    if (!navigator.geolocation) {
                        send(null, null);
                        return;
                    }

                    navigator.geolocation.getCurrentPosition(
                        (position) => send(position.coords.latitude, position.coords.longitude),
                        () => send(null, null),
                        { timeout: 10000 },
                    );
                },

                async postArrival(orderId, lat, lng) {
                    this.actionError = null;

                    try {
                        const response = await fetch(`/dashboard/orders/${orderId}/arrive`, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                Accept: 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                            },
                            body: JSON.stringify({ lat, lng }),
                        });

                        if (!response.ok) {
                            const payload = await response.json().catch(() => null);
                            throw new Error(payload?.message || 'Action failed');
                        }
                    } catch (e) {
                        this.actionError = e.message;
                    } finally {
                        this.arriving = null;
                        this.fetchData();
                    }
                },

                statusLabel(status) {
                    return {
                        ready: @js(__('Assigned to you, head to the branch')),
                        dispatched: @js(__('Out for delivery')),
                    }[status] ?? status;
                },

                // A rider only ever sees delivery orders (pickup skips the
                // rider entirely), so this is always the "collect cash at
                // the door" / "already settled online" distinction, never
                // the pickup wording.
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
