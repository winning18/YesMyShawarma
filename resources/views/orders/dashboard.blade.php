<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center" x-data="shiftWidget()" x-init="init()">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Orders') }}
            </h2>

            <div class="flex items-center gap-3 text-sm">
                <span x-show="active" class="text-gray-500">
                    {{ __('On shift') }} <span x-text="branch"></span>
                </span>
                <button
                    type="button"
                    x-show="!active"
                    @click="start()"
                    class="px-3 py-1.5 bg-gray-800 text-white text-sm font-semibold rounded-md hover:bg-gray-900"
                >{{ __('Start shift') }}</button>
                <button
                    type="button"
                    x-show="active"
                    @click="end()"
                    class="px-3 py-1.5 bg-gray-200 text-gray-800 text-sm font-semibold rounded-md hover:bg-gray-300"
                >{{ __('End shift') }}</button>
            </div>
        </div>
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

                <div class="space-y-4">
                    <template x-for="order in needsAcknowledgement" :key="order.id">
                        <div class="bg-white border-2 border-red-400 shadow-sm rounded-lg p-6">
                            <div class="flex justify-between items-start">
                                <div>
                                    <p class="font-semibold text-gray-900" x-text="order.reference"></p>
                                    <p class="text-sm text-gray-500" x-text="order.fulfilment_type + ' · ' + formatMoney(order.total)"></p>
                                    <p class="text-sm text-gray-500" x-text="order.customer_name || order.customer_phone"></p>
                                    <ul class="mt-2 text-sm text-gray-700 list-disc list-inside">
                                        <template x-for="item in order.items" :key="item.name + item.quantity">
                                            <li x-text="item.quantity + 'x ' + item.name + (item.options.length ? ' (' + item.options.join(', ') + ')' : '')"></li>
                                        </template>
                                    </ul>
                                </div>
                                <div class="flex gap-2">
                                    <button
                                        type="button"
                                        class="px-4 py-2 bg-green-600 text-white text-sm font-semibold rounded-md hover:bg-green-700"
                                        @click="act(order.id, 'accept')"
                                    >{{ __('Accept') }}</button>
                                    <button
                                        type="button"
                                        class="px-4 py-2 bg-gray-200 text-gray-800 text-sm font-semibold rounded-md hover:bg-gray-300"
                                        @click="act(order.id, 'reject')"
                                    >{{ __('Reject') }}</button>
                                </div>
                            </div>
                        </div>
                    </template>

                    <p x-show="needsAcknowledgement.length === 0" class="text-sm text-gray-500">{{ __('Nothing waiting.') }}</p>
                </div>
            </section>

            <section>
                <h3 class="text-lg font-semibold text-gray-800 mb-3">{{ __('In progress') }}</h3>

                <div class="space-y-4">
                    <template x-for="order in inProgress" :key="order.id">
                        <div class="bg-white shadow-sm rounded-lg p-6">
                            <div class="flex justify-between items-start">
                                <div>
                                    <p class="font-semibold text-gray-900" x-text="order.reference"></p>
                                    <p class="text-sm text-gray-500" x-text="order.status + ' · ' + order.fulfilment_type"></p>
                                </div>
                                <div class="flex gap-2 items-center">
                                    <template x-if="nextStatus(order.status)">
                                        <button
                                            type="button"
                                            class="px-4 py-2 bg-blue-600 text-white text-sm font-semibold rounded-md hover:bg-blue-700"
                                            @click="advance(order.id, nextStatus(order.status))"
                                            x-text="'Mark ' + nextStatus(order.status)"
                                        ></button>
                                    </template>
                                </div>
                            </div>
                        </div>
                    </template>

                    <p x-show="inProgress.length === 0" class="text-sm text-gray-500">{{ __('Nothing in progress.') }}</p>
                </div>
            </section>
        </div>
    </div>

    <script>
        function shiftWidget() {
            return {
                active: false,
                branch: null,

                init() {
                    this.refresh();
                },

                async refresh() {
                    const response = await fetch('{{ route('shift.show') }}', { headers: { Accept: 'application/json' } });
                    const data = await response.json();
                    this.active = data.active;
                    this.branch = data.branch;
                },

                async start() {
                    await this.post('{{ route('shift.start') }}');
                },

                async end() {
                    await this.post('{{ route('shift.end') }}');
                },

                async post(url) {
                    await fetch(url, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            Accept: 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        },
                        body: '{}',
                    });
                    this.refresh();
                },
            };
        }

        function orderDashboard(branchId) {
            return {
                needsAcknowledgement: [],
                inProgress: [],
                error: null,
                alarmTimer: null,
                originalTitle: document.title,

                init() {
                    this.fetchData();

                    if (branchId) {
                        window.Echo.private(`branch.${branchId}.orders`)
                            .listen('.OrderPlaced', () => this.fetchData())
                            .listen('.OrderStatusChanged', () => this.fetchData());
                    } else {
                        // Owner viewing an aggregate, cross-branch view has no
                        // single channel to subscribe to — fall back to polling.
                        setInterval(() => this.fetchData(), 20000);
                    }

                    setInterval(() => this.updateTitle(), 1000);
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
                        this.updateAlarm();
                    } catch (e) {
                        this.error = e.message;
                    }
                },

                async act(orderId, action) {
                    await this.post(`/dashboard/orders/${orderId}/${action}`);
                },

                async advance(orderId, to) {
                    await this.post(`/dashboard/orders/${orderId}/advance`, { to });
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

                nextStatus(status) {
                    return { accepted: 'preparing', preparing: 'ready', ready: 'dispatched', dispatched: 'delivered' }[status] ?? null;
                },

                formatMoney(pesewas) {
                    return 'GHS ' + (pesewas / 100).toFixed(2);
                },

                updateTitle() {
                    document.title = this.needsAcknowledgement.length > 0
                        ? `(${this.needsAcknowledgement.length}) ${this.originalTitle}`
                        : this.originalTitle;
                },

                // Repeating audible alarm while any order sits in "paid" — not a
                // single chime, a loop, per orders.md. Generated with the Web
                // Audio API rather than a bundled sound asset.
                updateAlarm() {
                    if (this.needsAcknowledgement.length > 0 && !this.alarmTimer) {
                        this.alarmTimer = setInterval(() => this.beep(), 1500);
                        this.beep();
                    } else if (this.needsAcknowledgement.length === 0 && this.alarmTimer) {
                        clearInterval(this.alarmTimer);
                        this.alarmTimer = null;
                    }
                },

                beep() {
                    const AudioContext = window.AudioContext || window.webkitAudioContext;
                    if (!AudioContext) return;

                    const ctx = new AudioContext();
                    const oscillator = ctx.createOscillator();
                    const gain = ctx.createGain();

                    oscillator.type = 'sine';
                    oscillator.frequency.value = 880;
                    gain.gain.value = 0.2;

                    oscillator.connect(gain);
                    gain.connect(ctx.destination);

                    oscillator.start();
                    oscillator.stop(ctx.currentTime + 0.3);
                },
            };
        }
    </script>
</x-app-layout>
