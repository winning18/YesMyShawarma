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
                                    <p class="text-sm text-gray-500" x-text="statusLabel(order.status)"></p>

                                    <p class="text-sm text-gray-700 mt-2" x-text="order.customer_name || 'Customer'"></p>
                                    <a class="text-sm text-blue-600 underline" :href="'tel:' + order.customer_phone" x-text="order.customer_phone"></a>

                                    <p class="text-sm text-gray-700 mt-2" x-show="order.delivery_address?.area_name" x-text="order.delivery_address?.area_name"></p>
                                    <p class="text-sm text-gray-500" x-show="order.delivery_address?.landmark" x-text="order.delivery_address?.landmark"></p>
                                </div>
                                <button
                                    type="button"
                                    class="shrink-0 px-4 py-2 bg-blue-600 text-white text-sm font-semibold rounded-md hover:bg-blue-700"
                                    x-show="nextAction(order.status)"
                                    @click="advance(order.id, nextAction(order.status).to)"
                                    x-text="nextAction(order.status)?.label"
                                ></button>
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

                statusLabel(status) {
                    return {
                        ready: @js(__('Assigned to you — head to the branch')),
                        dispatched: @js(__('Out for delivery')),
                    }[status] ?? status;
                },
            };
        }
    </script>
</x-rider-layout>
