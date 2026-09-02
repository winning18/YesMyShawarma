<x-app-layout>
    <x-slot name="header">
        @include('dashboard._channel-header', ['title' => __('Orders'), 'active' => 'orders', 'isStaff' => $isStaff, 'forceShiftStart' => $forceShiftStart, 'ordersUrl' => $ordersUrl, 'branchId' => $branchId])
    </x-slot>

    <div
        class="py-12"
        x-data="orderDashboard({{ $branchId ?? 'null' }})"
        x-init="init()"
    >
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-8">

            <template x-if="error">
                <div class="bg-red-50 border border-red-200 text-red-700 text-sm rounded-lg p-4" x-text="error"></div>
            </template>

            <section>
                <h3 class="text-lg font-semibold text-gray-800 mb-3">
                    {{ __('Needs acknowledgement') }}
                    <span x-show="needsAcknowledgement.length > 0" x-text="'(' + needsAcknowledgement.length + ')'"></span>
                </h3>

                <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
                    <template x-for="order in needsAcknowledgement" :key="order.id">
                        <div class="bg-white border-2 border-red-400 shadow-sm rounded-lg p-5 flex flex-col">
                            <div class="flex items-start justify-between gap-3 mb-3">
                                <div class="min-w-0">
                                    <p class="font-semibold text-gray-900 truncate" x-text="order.reference"></p>
                                    <div class="flex items-center gap-1.5 mt-1">
                                        <span class="text-xs font-medium px-2 py-0.5 rounded-full bg-gray-100 text-gray-600 capitalize" x-text="order.fulfilment_type"></span>
                                        <span
                                            class="text-xs font-medium px-2 py-0.5 rounded-full"
                                            :class="order.payment_method === 'paystack' ? 'bg-green-50 text-green-700' : 'bg-amber-50 text-amber-700'"
                                            x-text="paymentLabel(order)"
                                        ></span>
                                    </div>
                                </div>
                                <span class="shrink-0 font-semibold text-gray-900" x-text="formatMoney(order.total)"></span>
                            </div>

                            <div class="border-t border-gray-100 pt-3 mb-3">
                                <p class="text-sm font-medium text-gray-800" x-show="order.customer_name" x-text="order.customer_name"></p>
                                <a
                                    :href="'tel:' + order.customer_phone" x-text="order.customer_phone"
                                    class="text-sm text-blue-600 hover:underline"
                                ></a>
                                <p class="text-sm text-gray-500 mt-0.5" x-show="deliveryLine(order)" x-text="deliveryLine(order)"></p>
                            </div>

                            <ul class="text-sm text-gray-700 list-disc list-inside space-y-0.5 mb-4 flex-1">
                                <template x-for="item in order.items" :key="item.name + item.quantity">
                                    <li x-text="item.quantity + 'x ' + item.name + (item.options.length ? ' (' + item.options.join(', ') + ')' : '')"></li>
                                </template>
                            </ul>

                            <div class="flex gap-2 mt-auto">
                                <button
                                    type="button"
                                    class="flex-1 px-4 py-2 bg-green-600 text-white text-sm font-semibold rounded-md hover:bg-green-700"
                                    @click="act(order.id, 'accept')"
                                >{{ __('Accept') }}</button>
                                <button
                                    type="button"
                                    class="flex-1 px-4 py-2 bg-gray-200 text-gray-800 text-sm font-semibold rounded-md hover:bg-gray-300"
                                    @click="act(order.id, 'reject')"
                                >{{ __('Reject') }}</button>
                            </div>
                        </div>
                    </template>

                    <p x-show="needsAcknowledgement.length === 0" class="text-sm text-gray-500">{{ __('Nothing waiting.') }}</p>
                </div>
            </section>

            <section>
                <h3 class="text-lg font-semibold text-gray-800 mb-3">{{ __('In progress') }}</h3>

                <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
                    <template x-for="order in inProgress" :key="order.id">
                        <div class="bg-white shadow-sm rounded-lg p-5 flex flex-col">
                            <div class="flex items-start justify-between gap-3 mb-3">
                                <div class="min-w-0">
                                    <p class="font-semibold text-gray-900 truncate" x-text="order.reference"></p>
                                    <div class="flex items-center gap-1.5 mt-1 flex-wrap">
                                        <span class="text-xs font-medium px-2 py-0.5 rounded-full bg-blue-50 text-blue-700 capitalize" x-text="order.status"></span>
                                        <span class="text-xs font-medium px-2 py-0.5 rounded-full bg-gray-100 text-gray-600 capitalize" x-text="order.fulfilment_type"></span>
                                        <span
                                            class="text-xs font-medium px-2 py-0.5 rounded-full"
                                            :class="order.payment_method === 'paystack' ? 'bg-green-50 text-green-700' : 'bg-amber-50 text-amber-700'"
                                            x-text="paymentLabel(order)"
                                        ></span>
                                    </div>
                                </div>

                                {{--
                                    30-minute cooking countdown — only while
                                    actually 'preparing'. Ticks via the
                                    shared `now` clock (see script below) so
                                    every card on the board re-renders in
                                    lockstep, one interval rather than one
                                    per card. Depletes green from a full
                                    ring down to empty at 30:00; past that
                                    it locks full and flips red, and the
                                    label keeps counting up as overrun time
                                    ("+MM:SS") rather than stopping at zero
                                    — staff need to see *how* late, not just
                                    that it's late.
                                --}}
                                <template x-if="order.status === 'preparing' && order.preparing_at">
                                    <div class="shrink-0 relative w-12 h-12">
                                        <svg viewBox="0 0 40 40" class="w-12 h-12 -rotate-90">
                                            <circle cx="20" cy="20" r="16" fill="none" stroke="#e5e7eb" stroke-width="4" />
                                            <circle
                                                cx="20" cy="20" r="16" fill="none"
                                                stroke-width="4" stroke-linecap="round"
                                                :stroke="cookingOverrun(order) ? '#dc2626' : '#16a34a'"
                                                :stroke-dasharray="COOKING_RING_CIRCUMFERENCE"
                                                :stroke-dashoffset="COOKING_RING_CIRCUMFERENCE * (1 - cookingProgress(order))"
                                                style="transition: stroke-dashoffset 1s linear, stroke 0.3s"
                                            />
                                        </svg>
                                        <span
                                            class="absolute inset-0 flex items-center justify-center text-[10px] font-semibold"
                                            :class="cookingOverrun(order) ? 'text-red-600' : 'text-green-700'"
                                            x-text="cookingLabel(order)"
                                        ></span>
                                    </div>
                                </template>
                            </div>

                            <div class="border-t border-gray-100 pt-3 mb-3">
                                <p class="text-sm font-medium text-gray-800" x-show="order.customer_name" x-text="order.customer_name"></p>
                                <a
                                    :href="'tel:' + order.customer_phone" x-text="order.customer_phone"
                                    class="text-sm text-blue-600 hover:underline"
                                ></a>
                                <p class="text-sm text-gray-500 mt-0.5" x-show="deliveryLine(order)" x-text="deliveryLine(order)"></p>
                            </div>

                            <ul class="text-sm text-gray-700 list-disc list-inside space-y-0.5 mb-3">
                                <template x-for="item in order.items" :key="item.name + item.quantity">
                                    <li x-text="item.quantity + 'x ' + item.name + (item.options.length ? ' (' + item.options.join(', ') + ')' : '')"></li>
                                </template>
                            </ul>

                            <template x-if="needsRiderControl(order)">
                                <div class="text-sm mb-3">
                                    <span class="text-gray-500" x-show="order.rider_name">
                                        {{ __('Rider:') }} <span class="text-gray-900 font-medium" x-text="order.rider_name"></span>
                                    </span>
                                    <span class="text-gray-500" x-show="!order.rider_name">{{ __('No rider assigned') }}</span>

                                    <select
                                        class="block mt-1 text-sm rounded-md border-gray-300"
                                        x-show="riders.length > 0"
                                        @change="assignRider(order.id, $event.target.value); $event.target.value = ''"
                                    >
                                        <option value="" x-text="order.rider_name ? @js(__('Reassign to…')) : @js(__('Assign to…'))"></option>
                                        <template x-for="rider in riders" :key="rider.id">
                                            <option :value="rider.id" x-text="rider.name"></option>
                                        </template>
                                    </select>
                                    <p class="mt-1 text-xs text-red-600 font-medium" x-show="riders.length === 0">
                                        {{ __('No riders available') }}
                                    </p>
                                </div>
                            </template>

                            <div class="flex gap-2 items-center mt-auto pt-1">
                                <template x-if="nextStatus(order)">
                                    <button
                                        type="button"
                                        class="flex-1 px-4 py-2 bg-blue-600 text-white text-sm font-semibold rounded-md hover:bg-blue-700"
                                        @click="advancePrimary(order)"
                                        x-text="nextStatusLabel(order)"
                                    ></button>
                                </template>
                                <button
                                    type="button"
                                    class="flex-1 px-4 py-2 bg-red-50 text-red-700 text-sm font-semibold rounded-md hover:bg-red-100"
                                    x-show="order.status === 'dispatched' && order.fulfilment_type === 'delivery'"
                                    @click="if (confirm(@js(__('Mark this delivery as failed?')))) advance(order.id, 'failed')"
                                >{{ __('Delivery failed') }}</button>
                            </div>
                        </div>
                    </template>

                    <p x-show="inProgress.length === 0" class="text-sm text-gray-500">{{ __('Nothing in progress.') }}</p>
                </div>
            </section>
        </div>
    </div>

    <script>
        function orderDashboard(branchId) {
            return {
                needsAcknowledgement: [],
                inProgress: [],
                riders: [],
                error: null,
                originalTitle: document.title,
                now: Date.now(),
                COOKING_SECONDS: 30 * 60,
                COOKING_RING_CIRCUMFERENCE: 2 * Math.PI * 16,

                init() {
                    this.fetchData();
                    this.fetchRiders();

                    if (branchId) {
                        window.Echo.private(`branch.${branchId}.orders`)
                            .listen('.OrderPlaced', () => this.fetchData())
                            .listen('.OrderStatusChanged', () => this.fetchData());
                    } else {
                        // Owner viewing an aggregate, cross-branch view has no
                        // single channel to subscribe to — fall back to polling.
                        setInterval(() => this.fetchData(), 20000);
                    }

                    // Riders aren't refreshed in real time otherwise (only
                    // alongside an order fetch), so they're polled
                    // independently here too — "a rider just came on shift"
                    // is noticed promptly on its own, not only whenever the
                    // next order update happens to land.
                    setInterval(() => this.fetchRiders(), 15000);

                    // Drives updateTitle() below too — one shared 1s tick
                    // rather than a second interval doing the same thing.
                    setInterval(() => {
                        this.now = Date.now();
                        this.updateTitle();
                    }, 1000);
                },

                async fetchData() {
                    try {
                        const response = await fetch('{{ route('dashboard.orders.data') }}', {
                            headers: { Accept: 'application/json' },
                        });

                        if (!response.ok) throw new Error('Failed to load orders');

                        const { data } = await response.json();

                        this.needsAcknowledgement = data.filter(o => o.status === 'paid');
                        this.inProgress = data.filter(o => o.status !== 'paid');
                        this.error = null;
                        this.fetchRiders();
                    } catch (e) {
                        this.error = e.message;
                    }
                },

                // On-shift riders for the assign-rider dropdown — refreshed
                // alongside order data so it doesn't go stale as riders
                // start/end shifts during the session. Only ever holds
                // riders, never staff.
                async fetchRiders() {
                    const wasEmpty = this.riders.length === 0;

                    try {
                        const response = await fetch('{{ route('dashboard.riders') }}', {
                            headers: { Accept: 'application/json' },
                        });

                        if (!response.ok) return;

                        const { data } = await response.json();
                        this.riders = data;

                        // A rider going from none-available to available
                        // only matters if there's actually an order sitting
                        // there waiting for one — otherwise this fires every
                        // time the first rider of the day clocks in with
                        // nothing to assign them to.
                        if (wasEmpty && this.riders.length > 0 && this.hasUnassignedDelivery()) {
                            this.riderAvailableChime();
                        }
                    } catch (e) {
                        // Non-critical — the dropdown just stays empty.
                    }
                },

                needsRiderControl(order) {
                    return order.fulfilment_type === 'delivery' && ['ready', 'dispatched'].includes(order.status);
                },

                hasUnassignedDelivery() {
                    return this.inProgress.some(order => this.needsRiderControl(order) && !order.rider_name);
                },

                // Seconds left in the 30-minute cooking window — negative
                // once overrun, and deliberately left uncapped in that
                // direction (this.now ticking every second is what keeps
                // it counting rather than freezing at 00:00).
                cookingRemainingSeconds(order) {
                    const elapsed = (this.now - new Date(order.preparing_at).getTime()) / 1000;

                    return Math.floor(this.COOKING_SECONDS - elapsed);
                },

                cookingOverrun(order) {
                    return this.cookingRemainingSeconds(order) < 0;
                },

                // 1 (just started) down to 0 (out of time) — locked at 0
                // rather than going negative, since the ring itself has
                // nowhere further to deplete to; cookingOverrun() is what
                // flips it red once time's up, this only controls how much
                // of the ring is drawn.
                cookingProgress(order) {
                    return Math.max(0, Math.min(1, this.cookingRemainingSeconds(order) / this.COOKING_SECONDS));
                },

                // "MM:SS" while counting down, "+MM:SS" once overrun — the
                // sign matters more than the mm:ss/hh:mm distinction here,
                // an order is not meant to sit in 'preparing' long enough
                // for the overrun to reach an hour.
                cookingLabel(order) {
                    const remaining = this.cookingRemainingSeconds(order);
                    const overrun = remaining < 0;
                    const totalSeconds = Math.abs(remaining);
                    const minutes = String(Math.floor(totalSeconds / 60)).padStart(2, '0');
                    const seconds = String(totalSeconds % 60).padStart(2, '0');

                    return (overrun ? '+' : '') + minutes + ':' + seconds;
                },

                // Semantic address only (area, landmark) — never the raw
                // coordinates the customer shared at checkout. Staff and
                // managers don't need exact GPS to hand an order off; a
                // rider sees the precise location on their own delivery.
                deliveryLine(order) {
                    if (order.fulfilment_type !== 'delivery' || !order.delivery_address) return '';

                    return [order.delivery_address.area_name, order.delivery_address.landmark]
                        .filter(Boolean)
                        .join(', ');
                },

                // Cash still needs collecting on arrival — staff/riders
                // need to see that at a glance, distinct from Paystack
                // (already settled online) and POS's momo. Contextual
                // wording for cash (delivery vs pickup) matches how the
                // customer actually chose it at checkout.
                paymentLabel(order) {
                    if (order.payment_method === 'paystack') return @js(__('Paid via Paystack'));
                    if (order.payment_method === 'momo') return @js(__('Momo'));

                    return order.fulfilment_type === 'delivery'
                        ? @js(__('Cash on delivery'))
                        : @js(__('Cash on pickup'));
                },

                async act(orderId, action) {
                    await this.post(`/dashboard/orders/${orderId}/${action}`);
                },

                async advance(orderId, to) {
                    await this.post(`/dashboard/orders/${orderId}/advance`, { to });
                },

                // Staff can also mark a dispatched order delivered from
                // here, and cash-on-delivery still needs the same explicit
                // "money in hand" confirmation before it's counted as paid.
                // Never fires for pickup: nextStatus() never targets
                // 'delivered' directly for a pickup order (see below), and
                // pickup+cash is already reconciled at placement, so
                // there's nothing to confirm here.
                async advancePrimary(order) {
                    const to = this.nextStatus(order);
                    if (!to) return;

                    if (to === 'delivered' && order.payment_method === 'cash') {
                        const prompt = @js(__('Confirm cash payment of')) + ' ' + this.formatMoney(order.total) + ' ' + @js(__('has been collected for this order?'));
                        if (!confirm(prompt)) return;
                    }

                    await this.advance(order.id, to);

                    // Pickup has no rider and no separate "dispatched"
                    // concept exposed to staff — "dispatched" already means
                    // collected for pickup, so one click here ("Picked up")
                    // completes the order in full rather than leaving it
                    // stranded (only 'delivered' leaves the live board).
                    if (order.fulfilment_type === 'pickup' && to === 'dispatched') {
                        await this.advance(order.id, 'delivered');
                    }
                },

                async assignRider(orderId, riderId) {
                    if (!riderId) return;
                    await this.post(`/dashboard/orders/${orderId}/assign-rider`, { rider_id: riderId });
                },

                async post(url, body = {}) {
                    try {
                        const response = await fetch(url, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                Accept: 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                            },
                            body: JSON.stringify(body),
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

                // Pickup stops offering a next action at 'dispatched' —
                // that status already means "collected" for pickup (see
                // advancePrimary), there's nothing further to expose to
                // staff. Delivery keeps the full chain through 'delivered',
                // driven by the rider from their own dashboard past
                // 'dispatched' — staff's "Mark delivered" here is the
                // fallback for when a rider can't/doesn't do it themselves.
                nextStatus(order) {
                    const flow = order.fulfilment_type === 'pickup'
                        ? { accepted: 'preparing', preparing: 'ready', ready: 'dispatched' }
                        : { accepted: 'preparing', preparing: 'ready', ready: 'dispatched', dispatched: 'delivered' };

                    return flow[order.status] ?? null;
                },

                // "Accept order" itself lives in the Needs acknowledgement
                // section (a plain 'Accept' button, not this map) — this
                // only covers steps reachable after that. Pickup gets the
                // plain-language sequence requested: Start preparing ->
                // Ready for pickup -> Picked up. Delivery keeps the
                // existing generic "Mark <status>" wording.
                nextStatusLabel(order) {
                    const to = this.nextStatus(order);
                    if (!to) return '';

                    if (order.fulfilment_type === 'pickup') {
                        return {
                            preparing: @js(__('Start preparing')),
                            ready: @js(__('Ready for pickup')),
                            dispatched: @js(__('Picked up')),
                        }[to] ?? to;
                    }

                    return @js(__('Mark')) + ' ' + to;
                },

                formatMoney(pesewas) {
                    return 'GHS ' + (pesewas / 100).toFixed(2);
                },

                // The alarm itself now lives in a shared widget so it
                // plays on POS and Order History too, not just this page —
                // this only still owns the tab title, which is specific to
                // actually being on this page.
                updateTitle() {
                    document.title = this.needsAcknowledgement.length > 0
                        ? `(${this.needsAcknowledgement.length}) ${this.originalTitle}`
                        : this.originalTitle;
                },

                // A single two-note chime, not a repeating alarm — this is
                // "someone's now free to assign", not "you're missing
                // something urgent" (that's the unacknowledged-order alarm
                // in the shared header). Deliberately a different pitch/
                // shape from that one so the two are distinguishable by
                // ear, not just by context.
                riderAvailableChime() {
                    const AudioContext = window.AudioContext || window.webkitAudioContext;
                    if (!AudioContext) return;

                    const ctx = new AudioContext();
                    [660, 990].forEach((frequency, i) => {
                        const oscillator = ctx.createOscillator();
                        const gain = ctx.createGain();

                        oscillator.type = 'sine';
                        oscillator.frequency.value = frequency;
                        gain.gain.value = 0.2;

                        oscillator.connect(gain);
                        gain.connect(ctx.destination);

                        const start = ctx.currentTime + i * 0.15;
                        oscillator.start(start);
                        oscillator.stop(start + 0.15);
                    });
                },
            };
        }
    </script>
</x-app-layout>
