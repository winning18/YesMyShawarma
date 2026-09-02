<x-app-layout>
    <x-slot name="header">
        @include('dashboard._channel-header', ['title' => __('POS'), 'active' => 'pos', 'isStaff' => $isStaff, 'forceShiftStart' => $forceShiftStart, 'ordersUrl' => $ordersUrl, 'branchId' => $branch->id])
    </x-slot>

    <div
        class="py-8"
        x-data="posTerminal()"
        x-init="init()"
    >
        {{--
            lg+ only: each column scrolls independently within its own fixed
            viewport-relative height, rather than the whole page scrolling —
            a POS terminal is a fixed-height, landscape/desktop tool, so the
            menu grid and the cart/checkout panel each keep their own
            scrollbar. Below lg, columns stack and the page scrolls normally
            (cramming two independent scroll regions into a phone-width
            screen is worse, not better).
        --}}
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 grid grid-cols-1 lg:grid-cols-3 gap-8 lg:h-[calc(100vh-6rem)]">
            {{-- Menu picker --}}
            <div class="lg:col-span-2 lg:h-full lg:overflow-y-auto lg:pr-2 space-y-8">
                <p class="text-sm text-gray-500">{{ $branch->name }}</p>

                @forelse ($categories as $group)
                    <section>
                        <h3 class="font-semibold text-gray-800 mb-3">{{ $group['category']->name }}</h3>
                        <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                            @foreach ($group['items'] as $item)
                                <button
                                    type="button"
                                    @click="openItem(@js([
                                        'id' => $item->id,
                                        'name' => $item->name,
                                        'price' => $item->base_price,
                                        'optionGroups' => $item->optionGroups->map(fn ($g) => [
                                            'id' => $g->id,
                                            'name' => $g->name,
                                            'min_select' => $g->min_select,
                                            'max_select' => $g->max_select,
                                            'is_required' => $g->is_required,
                                            'options' => $g->options->map(fn ($o) => [
                                                'id' => $o->id, 'name' => $o->name, 'price_delta' => $o->price_delta,
                                            ])->values(),
                                        ])->values(),
                                    ]))"
                                    class="bg-white border border-gray-200 rounded-lg p-3 text-left hover:border-gray-400 flex flex-col gap-2"
                                >
                                    <x-product-image :item="$item" class="w-full" />
                                    <div>
                                        <p class="text-sm font-semibold text-gray-800 truncate">{{ $item->name }}</p>
                                        <p class="text-sm text-gray-500">GH₵{{ number_format($item->base_price / 100, 2) }}</p>
                                    </div>
                                </button>
                            @endforeach
                        </div>
                    </section>
                @empty
                    <p class="text-sm text-gray-500">{{ __('Nothing available to sell at this branch right now.') }}</p>
                @endforelse
            </div>

            {{-- Cart + checkout --}}
            <div class="bg-white shadow rounded-lg p-4 lg:h-full lg:overflow-y-auto">
                <h3 class="font-semibold text-gray-800 mb-3">{{ __('Order') }}</h3>

                <p class="text-sm text-gray-500" x-show="lines.length === 0">{{ __('No items yet.') }}</p>

                <ul class="space-y-3 mb-4 text-sm" x-show="lines.length > 0">
                    <template x-for="line in lines" :key="line.line_id">
                        <li class="flex justify-between items-start gap-2">
                            <div class="flex-1 min-w-0">
                                <p class="text-gray-800 truncate" x-text="line.name_snapshot"></p>
                                <p class="text-xs text-gray-500" x-show="line.options.length" x-text="line.options.map(o => o.name_snapshot).join(', ')"></p>
                                <div class="flex items-center gap-2 mt-1">
                                    <button type="button" @click="changeQuantity(line.line_id, line.quantity - 1)" class="w-5 h-5 flex items-center justify-center border border-gray-300 rounded text-xs text-gray-600">-</button>
                                    <span class="text-xs w-4 text-center" x-text="line.quantity"></span>
                                    <button type="button" @click="changeQuantity(line.line_id, line.quantity + 1)" class="w-5 h-5 flex items-center justify-center border border-gray-300 rounded text-xs text-gray-600">+</button>
                                </div>
                            </div>
                            <div class="text-right shrink-0">
                                <p class="text-gray-800" x-text="formatMoney(line.line_total)"></p>
                                <button type="button" @click="removeLine(line.line_id)" class="text-xs text-red-600 hover:underline mt-1">{{ __('Remove') }}</button>
                            </div>
                        </li>
                    </template>
                </ul>

                <div class="flex justify-between font-semibold border-t border-gray-100 pt-3 mb-4">
                    <span>{{ __('Subtotal') }}</span>
                    <span x-text="formatMoney(subtotal)"></span>
                </div>

                <div class="space-y-3 border-t border-gray-100 pt-4">
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('Customer phone') }} <span class="text-red-600">*</span></label>
                        <input
                            type="tel" inputmode="numeric" autocomplete="off"
                            :value="phone.formatted" @input="phone.onInput($event)" @blur="phone.onBlur()"
                            placeholder="024-123-4567" maxlength="12"
                            class="w-full rounded-md text-sm"
                            :class="phone.invalid ? 'border-red-500 ring-1 ring-red-500' : 'border-gray-300'"
                        >
                        <p class="text-xs text-red-600 mt-1" x-show="phone.invalid">{{ __('Enter a 10-digit phone number.') }}</p>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('Customer name (optional)') }}</label>
                        <input type="text" x-model="name" class="w-full rounded-md border-gray-300 text-sm">
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-2">{{ __('Fulfilment') }} <span class="text-red-600">*</span></label>
                        <div class="flex gap-4 text-sm">
                            <label class="flex items-center gap-1.5">
                                <input type="radio" x-model="fulfilmentType" value="pickup"> {{ __('Pickup') }}
                            </label>
                            @if ($deliveryAvailable)
                                <label class="flex items-center gap-1.5">
                                    <input type="radio" x-model="fulfilmentType" value="delivery"> {{ __('Delivery') }}
                                </label>
                            @endif
                        </div>
                    </div>

                    @if ($deliveryAvailable)
                        <div x-show="fulfilmentType === 'delivery'" x-cloak class="space-y-3">
                            <div>
                                <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('Delivery area') }} <span class="text-red-600">*</span></label>
                                <select x-model="areaId" class="w-full rounded-md border-gray-300 text-sm">
                                    <option value="">{{ __('Select…') }}</option>
                                    @foreach ($deliveryAreas as $area)
                                        <option value="{{ $area->id }}">{{ $area->name }}</option>
                                    @endforeach
                                    <option value="other">{{ __('Other (not listed)') }}</option>
                                </select>
                            </div>
                            <div x-show="areaId === 'other'" x-cloak>
                                <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('Area name') }}</label>
                                <input type="text" x-model="areaOther" class="w-full rounded-md border-gray-300 text-sm">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('Landmark') }} <span class="text-red-600">*</span></label>
                                <input type="text" x-model="landmark" class="w-full rounded-md border-gray-300 text-sm">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('GhanaPost GPS code (optional)') }}</label>
                                <input type="text" x-model="ghanapostCode" class="w-full rounded-md border-gray-300 text-sm">
                            </div>
                            <p class="text-xs text-gray-500">{{ __('No live location capture at POS. The delivery fee is priced when the rider marks it delivered.') }}</p>
                        </div>
                    @endif

                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-2">{{ __('Payment') }} <span class="text-red-600">*</span></label>
                        <div class="flex gap-4 text-sm">
                            <label class="flex items-center gap-1.5">
                                <input type="radio" x-model="paymentMethod" value="cash"> {{ __('Cash') }}
                            </label>
                            <label class="flex items-center gap-1.5">
                                <input type="radio" x-model="paymentMethod" value="momo"> {{ __('Momo') }}
                            </label>
                        </div>
                    </div>

                    <div x-show="paymentMethod === 'momo'" x-cloak>
                        <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('Momo transaction ID') }}</label>
                        <div class="flex gap-2">
                            <input type="text" x-model="paymentReference" class="w-full rounded-md border-gray-300 text-sm" placeholder="{{ __('e.g. from the Momo confirmation SMS') }}">
                            <button
                                type="button" @click="paymentReference = ''"
                                x-show="paymentReference"
                                class="shrink-0 px-3 py-1.5 text-xs text-gray-600 border border-gray-300 rounded-md hover:bg-gray-50"
                            >{{ __('Skip') }}</button>
                        </div>
                        <p class="text-xs text-gray-500 mt-1">{{ __("Busy? Leave this blank and enter it later from the order. It won't be marked paid until you do.") }}</p>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('Instructions (optional)') }}</label>
                        <textarea x-model="instructions" rows="2" class="w-full rounded-md border-gray-300 text-sm"></textarea>
                    </div>

                    <div x-show="error" x-cloak class="bg-red-50 border border-red-200 text-red-700 text-sm rounded-lg p-3" x-text="error"></div>

                    <button
                        type="button" @click="placeOrder()"
                        :disabled="lines.length === 0 || submitting"
                        class="w-full px-4 py-2.5 bg-green-600 text-white text-sm font-semibold rounded-md hover:bg-green-700 disabled:opacity-50"
                    ><span x-text="submitting ? '{{ __('Placing…') }}' : '{{ __('Place order') }}'"></span></button>
                </div>
            </div>
        </div>

        {{-- Item options modal --}}
        <div x-show="activeItem" x-cloak class="fixed inset-0 z-40 flex items-center justify-center p-4">
            <div class="fixed inset-0 bg-black/50" @click="activeItem = null"></div>

            <template x-if="activeItem">
                <div class="relative bg-white rounded-lg shadow-lg max-w-md w-full p-5">
                    <h3 class="font-semibold text-gray-800 mb-1" x-text="activeItem.name"></h3>
                    <p class="text-sm text-gray-500 mb-4" x-text="formatMoney(activeItem.price)"></p>

                    <template x-for="group in activeItem.optionGroups" :key="group.id">
                        <fieldset class="mb-4">
                            <legend class="text-sm text-brand-yellow-dark font-semibold mb-1.5">
                                <span x-text="group.name"></span><span x-show="group.is_required" class="text-red-600"> *</span>
                            </legend>
                            <div class="grid grid-cols-2 gap-2">
                                <template x-for="option in group.options" :key="option.id">
                                    <label class="flex items-center gap-1.5 text-sm">
                                        <input
                                            type="checkbox" :checked="selectedOptionIds.includes(option.id)"
                                            @change="toggleOption(option.id, group.id)"
                                            class="rounded border-gray-300"
                                        >
                                        <span x-text="option.name + (option.price_delta ? ' (+' + formatMoney(option.price_delta) + ')' : '')"></span>
                                    </label>
                                </template>
                            </div>
                        </fieldset>
                    </template>

                    <div class="flex items-center gap-3 mb-4">
                        <label class="text-sm font-medium text-brand-yellow-dark">{{ __('Qty') }}</label>
                        <input type="number" x-model.number="itemQuantity" min="1" max="{{ \App\Services\Cart\PosCartService::MAX_LINE_QUANTITY }}" class="w-20 rounded-md border-gray-300 text-sm">
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <button type="button" @click="activeItem = null" class="px-4 py-2 border border-gray-300 rounded-md text-sm">{{ __('Cancel') }}</button>
                        <button type="button" @click="confirmAddItem()" class="px-4 py-2 bg-gray-800 text-white rounded-md text-sm hover:bg-gray-900">{{ __('Add') }}</button>
                    </div>
                </div>
            </template>
        </div>

        {{-- Confirmation --}}
        <div x-show="confirmation" x-cloak class="fixed inset-0 z-40 flex items-center justify-center p-4">
            <div class="fixed inset-0 bg-black/50"></div>

            <template x-if="confirmation">
                <div class="relative bg-white rounded-lg shadow-lg max-w-md w-full p-6 text-center">
                    <h3 class="font-semibold text-lg text-gray-800 mb-1">{{ __('Order placed') }}</h3>
                    <p class="text-sm text-gray-500 mb-1" x-text="confirmation.reference"></p>
                    <p class="text-xl font-bold text-gray-800 mb-4" x-text="formatMoney(confirmation.total)"></p>

                    <button type="button" @click="confirmation = null" class="w-full px-4 py-2 bg-gray-800 text-white rounded-md text-sm hover:bg-gray-900">{{ __('New order') }}</button>
                </div>
            </template>
        </div>
    </div>

    <script>
        function posTerminal() {
            return {
                lines: @json($cart['lines']),
                subtotal: {{ $cart['subtotal'] }},
                phone: phoneField(''),
                name: '',
                fulfilmentType: 'pickup',
                areaId: '',
                areaOther: '',
                landmark: '',
                ghanapostCode: '',
                paymentMethod: 'cash',
                paymentReference: '',
                instructions: '',
                error: null,
                submitting: false,
                activeItem: null,
                selectedOptionIds: [],
                itemQuantity: 1,
                confirmation: null,

                init() {},

                openItem(item) {
                    this.activeItem = item;
                    this.selectedOptionIds = [];
                    this.itemQuantity = 1;
                },

                toggleOption(id, groupId) {
                    const alreadySelected = this.selectedOptionIds.includes(id);
                    const group = this.activeItem.optionGroups.find((g) => g.id === groupId);

                    if (group && group.max_select === 1 && !alreadySelected) {
                        const otherIdsInGroup = group.options.map((o) => o.id);
                        this.selectedOptionIds = this.selectedOptionIds.filter((x) => !otherIdsInGroup.includes(x));
                        this.selectedOptionIds.push(id);
                        return;
                    }

                    this.selectedOptionIds = alreadySelected
                        ? this.selectedOptionIds.filter((x) => x !== id)
                        : [...this.selectedOptionIds, id];
                },

                async confirmAddItem() {
                    await this.mutateCart('{{ route('dashboard.pos.cart.add') }}', 'POST', {
                        menu_item_id: this.activeItem.id,
                        quantity: this.itemQuantity,
                        option_ids: this.selectedOptionIds,
                    });
                    this.activeItem = null;
                },

                async changeQuantity(lineId, quantity) {
                    if (quantity < 1) {
                        return this.removeLine(lineId);
                    }
                    await this.mutateCart(`/dashboard/pos/cart/${lineId}`, 'POST', { quantity });
                },

                async removeLine(lineId) {
                    await this.mutateCart(`/dashboard/pos/cart/${lineId}`, 'DELETE');
                },

                async mutateCart(url, method, body = null) {
                    try {
                        const response = await fetch(url, {
                            method,
                            headers: this.headers(),
                            body: body ? JSON.stringify(body) : undefined,
                        });
                        const payload = await response.json();

                        if (!response.ok) {
                            this.error = payload.message || '{{ __('Action failed.') }}';
                            return;
                        }

                        this.lines = payload.lines;
                        this.subtotal = payload.subtotal;
                        this.error = null;
                    } catch (e) {
                        this.error = '{{ __('Action failed.') }}';
                    }
                },

                async placeOrder() {
                    if (this.lines.length === 0 || this.submitting) return;

                    this.error = this.validationError();
                    if (this.error) return;

                    if (this.paymentMethod === 'cash' && !confirm(@js(__('Confirm you have received cash payment for this order?')))) {
                        return;
                    }

                    this.submitting = true;
                    this.error = null;

                    try {
                        const response = await fetch('{{ route('dashboard.pos.orders.store') }}', {
                            method: 'POST',
                            headers: this.headers(),
                            body: JSON.stringify({
                                phone: this.phone.raw,
                                name: this.name,
                                fulfilment_type: this.fulfilmentType,
                                area_id: this.areaId,
                                area_other: this.areaOther,
                                landmark: this.landmark,
                                ghanapost_code: this.ghanapostCode,
                                payment_method: this.paymentMethod,
                                payment_reference: this.paymentMethod === 'momo' ? this.paymentReference : null,
                                instructions: this.instructions,
                            }),
                        });

                        const payload = await response.json();

                        if (!response.ok) {
                            this.error = payload.message || '{{ __('Could not place the order.') }}';
                            return;
                        }

                        this.confirmation = {
                            reference: payload.order.reference,
                            total: payload.order.total,
                        };
                        this.resetForm();
                    } catch (e) {
                        this.error = '{{ __('Could not place the order.') }}';
                    } finally {
                        this.submitting = false;
                    }
                },

                // Mirrors the server-side validation rules — a faster
                // front door only, the server-side check stays the actual
                // authority.
                validationError() {
                    if (!this.phone.valid) return '{{ __('Enter a valid 10-digit customer phone number.') }}';

                    if (this.fulfilmentType === 'delivery') {
                        if (!this.areaId) return '{{ __('Delivery area is required.') }}';
                        if (this.areaId === 'other' && !this.areaOther.trim()) return '{{ __('Area name is required.') }}';
                        if (!this.landmark.trim()) return '{{ __('Landmark is required.') }}';
                    }

                    return null;
                },

                resetForm() {
                    this.lines = [];
                    this.subtotal = 0;
                    this.phone = phoneField('');
                    this.name = '';
                    this.fulfilmentType = 'pickup';
                    this.areaId = '';
                    this.areaOther = '';
                    this.landmark = '';
                    this.ghanapostCode = '';
                    this.paymentMethod = 'cash';
                    this.paymentReference = '';
                    this.instructions = '';
                },

                headers() {
                    return {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                    };
                },

                formatMoney(pesewas) {
                    return 'GH₵' + ((pesewas ?? 0) / 100).toFixed(2);
                },
            };
        }
    </script>

    @include('partials.phone-input-script')
</x-app-layout>
