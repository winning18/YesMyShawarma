<x-customer-layout title="Checkout · {{ config('app.name') }}">
    <h1 class="text-2xl font-bold mb-6">{{ __('Checkout') }}</h1>

    @if ($errors->any())
        <div class="mb-6 rounded-lg bg-brand-red-light border border-brand-red text-brand-red-dark text-sm px-4 py-2 space-y-1">
            @foreach ($errors->all() as $message)
                <p>{{ $message }}</p>
            @endforeach
        </div>
    @endif

    @unless ($branchOpen)
        <div class="mb-6 rounded-lg bg-brand-yellow-light border border-brand-yellow text-brand-black text-sm px-4 py-3">
            <p class="font-semibold">{{ __("We're currently closed.") }}</p>
            <p>
                @if ($nextOpeningLabel)
                    {{ __('You can still place your order. We\'ll start preparing it when we reopen :time.', ['time' => $nextOpeningLabel]) }}
                @else
                    {{ __('You can still place your order. We\'ll start preparing it at our next opening.') }}
                @endif
            </p>
        </div>
    @endunless

    {{--
        One shared Alpine scope for the whole page (not just the form) —
        the Order Summary column needs to react to fulfilmentType and
        estimatedFee too, and Alpine scope only flows down the DOM tree, so
        this has to sit above both columns rather than on the <form> alone.
    --}}
    <div
        class="grid grid-cols-1 md:grid-cols-3 gap-8"
        x-data="{
            fulfilmentType: '{{ old('fulfilment_type', 'pickup') }}',
            paymentMethod: 'cash',
            areaSelection: '{{ old('area_id', '') }}',
            lat: null,
            lng: null,
            locationStatus: '',
            estimatedFee: null,
            branchLat: {{ $branch->lat }},
            branchLng: {{ $branch->lng }},
            ratePerKmPesewas: {{ $ratePerKmPesewas }},
            subtotalPesewas: {{ $subtotal }},
            discountCode: @js(old('promo_code', '')),
            discountAmount: 0,
            promoMessage: '',
            reviewing: false,
            name: @js(old('name', $customer->name ?? '')),
            phone: phoneField(@js(old('phone', $customer->phone ?? ''))),
            landmark: @js(old('landmark', '')),
            ghanapostCode: @js(old('ghanapost_code', '')),
            areaOther: @js(old('area_other', '')),
            instructions: @js(old('instructions', '')),
            deliveryAreaNames: @js($deliveryAreas->pluck('name', 'id')),
            areaLabel() {
                if (this.areaSelection === 'other') return this.areaOther || '{{ __('Not specified') }}';
                return this.deliveryAreaNames[this.areaSelection] || '';
            },
            // Drives both the disabled/greyed-out state of 'Review order'
            // and startReview()'s own gate below — payment method and
            // fulfilment type always carry a valid default (a radio pair,
            // never genuinely empty), so only the fields a customer can
            // actually leave incomplete are checked here.
            get formValid() {
                if (this.name.trim() === '' || !this.phone.valid) return false;

                if (this.fulfilmentType === 'delivery') {
                    if (this.areaSelection === '') return false;
                    if (this.areaSelection === 'other' && this.areaOther.trim() === '') return false;
                    if (this.landmark.trim() === '') return false;
                }

                return true;
            },
            startReview() {
                if (!this.formValid || !this.$refs.form.reportValidity()) return;
                this.reviewing = true;
                window.scrollTo({ top: 0, behavior: 'smooth' });
            },
            locate() {
                if (!navigator.geolocation) {
                    this.locationStatus = '{{ __('Geolocation not supported on this device. Delivery fee will be calculated when your rider arrives.') }}';
                    return;
                }
                this.locationStatus = '{{ __('Locating…') }}';
                navigator.geolocation.getCurrentPosition(
                    (position) => {
                        this.lat = position.coords.latitude;
                        this.lng = position.coords.longitude;
                        this.estimatedFee = this.calculateFee();
                        this.locationStatus = '{{ __('Location captured. Estimated fee added below.') }}';
                    },
                    () => {
                        this.estimatedFee = null;
                        this.locationStatus = '{{ __('Could not get your location. Delivery fee will be calculated when your rider arrives.') }}';
                    }
                );
            },
            calculateFee() {
                const toRad = (deg) => deg * Math.PI / 180;
                const earthRadiusKm = 6371;
                const dLat = toRad(this.lat - this.branchLat);
                const dLng = toRad(this.lng - this.branchLng);
                const a = Math.sin(dLat / 2) ** 2
                    + Math.cos(toRad(this.branchLat)) * Math.cos(toRad(this.lat)) * Math.sin(dLng / 2) ** 2;
                const distanceKm = earthRadiusKm * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
                return Math.round(distanceKm * this.ratePerKmPesewas);
            },
            async applyPromoCode() {
                if (!this.discountCode) return;

                try {
                    const response = await fetch('{{ route('checkout.apply-promo') }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            Accept: 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                        },
                        body: JSON.stringify({ code: this.discountCode, phone: this.phone.raw }),
                    });
                    const payload = await response.json();

                    if (!response.ok) {
                        this.discountAmount = 0;
                        this.promoMessage = payload.message || '{{ __('This promo code is not valid.') }}';
                        return;
                    }

                    this.discountAmount = payload.discount;
                    this.promoMessage = '{{ __('Code applied.') }}';
                } catch (e) {
                    this.discountAmount = 0;
                    this.promoMessage = '{{ __('Could not check this code. Try again.') }}';
                }
            },
            // The only place delivery fee, discount, and subtotal actually
            // combine — see orders.md's totals order (subtotal - discount +
            // delivery fee), never below zero.
            estimatedTotal() {
                const fee = (this.fulfilmentType === 'delivery' && this.estimatedFee !== null) ? this.estimatedFee : 0;

                return Math.max(0, this.subtotalPesewas - this.discountAmount + fee);
            },
        }"
        x-effect="if (fulfilmentType !== 'delivery') { estimatedFee = null; locationStatus = ''; }"
    >
        <div class="md:col-span-2">
            <form id="checkout-form" method="POST" action="{{ route('checkout.store') }}" class="space-y-6" x-ref="form">
                @csrf

                <div x-show="!reviewing">
                <div>
                    <label class="block text-sm font-medium mb-1">{{ __('Name') }} <span class="text-brand-red">*</span></label>
                    <input
                        type="text" name="name" required
                        x-model="name"
                        value="{{ old('name', $customer->name ?? '') }}"
                        placeholder="{{ __('e.g. Ama Mensah') }}"
                        class="w-full rounded-md {{ $errors->has('name') ? 'border-brand-red ring-1 ring-brand-red' : 'border-brand-gray-300' }}"
                    >
                    @error('name') <p class="text-sm text-brand-red mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium mb-1">{{ __('Phone number') }} <span class="text-brand-red">*</span></label>
                    <input
                        type="tel" name="phone" inputmode="numeric" autocomplete="tel" required
                        :value="phone.formatted" @input="phone.onInput($event)" @blur="phone.onBlur()"
                        placeholder="024-123-4567" maxlength="12"
                        class="w-full rounded-md"
                        :class="(phone.invalid || {{ $errors->has('phone') ? 'true' : 'false' }}) ? 'border-brand-red ring-1 ring-brand-red' : 'border-brand-gray-300'"
                    >
                    <p class="text-xs text-brand-red mt-1" x-show="phone.invalid">{{ __('Enter a 10-digit phone number.') }}</p>
                    @error('phone') <p class="text-sm text-brand-red mt-1">{{ $message }}</p> @enderror
                </div>

                @if ($deliveryAvailable)
                    <div>
                        <label class="block text-sm font-medium mb-2">{{ __('Fulfilment') }} <span class="text-brand-red">*</span></label>
                        <div class="flex gap-6 text-sm">
                            <label class="flex items-center gap-2">
                                <input type="radio" name="fulfilment_type" value="pickup" x-model="fulfilmentType">
                                {{ __('Pickup') }}
                            </label>
                            <label class="flex items-center gap-2">
                                <input type="radio" name="fulfilment_type" value="delivery" x-model="fulfilmentType">
                                {{ __('Delivery') }}
                            </label>
                        </div>
                    </div>

                    <div x-show="fulfilmentType === 'delivery'" x-cloak class="space-y-3 border border-brand-gray-100 rounded-lg p-4">
                        <div>
                            <label class="block text-sm font-medium mb-1">{{ __('Delivery area') }} <span class="text-brand-red">*</span></label>
                            <select
                                name="area_id"
                                x-model="areaSelection"
                                class="w-full rounded-md {{ $errors->has('area_id') ? 'border-brand-red ring-1 ring-brand-red' : 'border-brand-gray-300' }}"
                            >
                                <option value="">{{ __('Select your area…') }}</option>
                                @foreach ($deliveryAreas as $area)
                                    <option value="{{ $area->id }}" @selected(old('area_id') == $area->id)>
                                        {{ $area->name }}
                                    </option>
                                @endforeach
                                <option value="other" @selected(old('area_id') === 'other')>{{ __("Other (not listed)") }}</option>
                            </select>
                            @error('area_id') <p class="text-sm text-brand-red mt-1">{{ $message }}</p> @enderror

                            <div x-show="areaSelection === 'other'" x-cloak class="mt-2">
                                <input
                                    type="text" name="area_other" x-model="areaOther" value="{{ old('area_other') }}"
                                    placeholder="{{ __('Type your area name') }}" maxlength="100"
                                    class="w-full rounded-md {{ $errors->has('area_other') ? 'border-brand-red ring-1 ring-brand-red' : 'border-brand-gray-300' }}"
                                >
                                @error('area_other') <p class="text-sm text-brand-red mt-1">{{ $message }}</p> @enderror
                            </div>

                            <p class="text-xs text-brand-gray-500 mt-1">{{ __("Just tells our rider roughly where you are. Your fee is based on your shared location below.") }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1">{{ __('GhanaPost GPS code (optional)') }}</label>
                            <input
                                type="text" name="ghanapost_code" x-model="ghanapostCode" value="{{ old('ghanapost_code') }}"
                                placeholder="{{ __('e.g. GA-123-4567') }}"
                                class="w-full rounded-md {{ $errors->has('ghanapost_code') ? 'border-brand-red ring-1 ring-brand-red' : 'border-brand-gray-300' }}"
                            >
                            @error('ghanapost_code') <p class="text-sm text-brand-red mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1">{{ __('Landmark') }} <span class="text-brand-red">*</span></label>
                            <input
                                type="text" name="landmark" x-model="landmark" value="{{ old('landmark') }}"
                                placeholder="{{ __('e.g. Opposite the blue gate, near the church') }}"
                                class="w-full rounded-md {{ $errors->has('landmark') ? 'border-brand-red ring-1 ring-brand-red' : 'border-brand-gray-300' }}"
                            >
                            @error('landmark') <p class="text-sm text-brand-red mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <button
                                type="button" @click="locate()"
                                class="w-full px-4 py-2.5 bg-brand-red text-brand-white text-sm font-semibold rounded-md hover:bg-brand-red-dark"
                            >
                                {{ __('Share my live location (required)') }}
                            </button>
                            <span x-show="locationStatus" x-text="locationStatus" class="block text-sm text-brand-gray-500 mt-1"></span>
                            <input type="hidden" name="lat" x-model="lat" value="{{ old('lat') }}">
                            <input type="hidden" name="lng" x-model="lng" value="{{ old('lng') }}">
                        </div>
                        <p class="text-xs text-brand-gray-500">
                            {{ __("If we can capture your location, you'll see your estimated delivery fee added below right away. If not, it's calculated in cash when your rider arrives.") }}
                        </p>
                    </div>
                @endif

                <div>
                    <label class="block text-sm font-medium mb-1">{{ __('Instructions (optional)') }}</label>
                    <textarea name="instructions" x-model="instructions" rows="3" maxlength="1000" class="w-full rounded-md border-brand-gray-300" placeholder="{{ __('e.g. call on arrival, leave at the gate…') }}">{{ old('instructions') }}</textarea>
                </div>

                <div>
                    <label class="block text-sm font-medium mb-2">{{ __('Payment method') }} <span class="text-brand-red">*</span></label>
                    <div class="flex gap-6 text-sm">
                        <label class="flex items-center gap-2">
                            <input type="radio" name="payment_method" value="cash" x-model="paymentMethod">
                            {{ __('Cash on delivery / pickup') }}
                        </label>
                        <label class="flex items-center gap-2">
                            <input type="radio" name="payment_method" value="paystack" x-model="paymentMethod">
                            {{ __('Pay now (card / mobile money)') }}
                        </label>
                    </div>
                    <p x-show="fulfilmentType === 'delivery' && paymentMethod === 'paystack' && estimatedFee === null" x-cloak class="text-xs text-brand-gray-500 mt-1">
                        {{ __("We'll charge the order subtotal now. If your delivery fee ends up being calculated when your rider arrives, that part is settled in cash.") }}
                    </p>
                </div>
                </div>

                {{-- Review step: same page, same form — just a read-only summary of
                     what's about to be submitted, swapped in via Alpine before the
                     real POST fires. Avoids a second page load on a slow connection. --}}
                <div x-show="reviewing" x-cloak class="space-y-4 border border-brand-gray-100 rounded-lg p-4">
                    <h2 class="font-semibold">{{ __('Review your order') }}</h2>

                    <dl class="text-sm space-y-2">
                        <div class="flex justify-between">
                            <dt class="text-brand-gray-500">{{ __('Name') }}</dt>
                            <dd x-text="name"></dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-brand-gray-500">{{ __('Phone number') }}</dt>
                            <dd x-text="phone.formatted"></dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-brand-gray-500">{{ __('Fulfilment') }}</dt>
                            <dd x-text="fulfilmentType === 'delivery' ? '{{ __('Delivery') }}' : '{{ __('Pickup') }}'"></dd>
                        </div>

                        <template x-if="fulfilmentType === 'delivery'">
                            <div class="space-y-2">
                                <div class="flex justify-between">
                                    <dt class="text-brand-gray-500">{{ __('Delivery area') }}</dt>
                                    <dd x-text="areaLabel()"></dd>
                                </div>
                                <div class="flex justify-between" x-show="landmark">
                                    <dt class="text-brand-gray-500">{{ __('Landmark') }}</dt>
                                    <dd x-text="landmark"></dd>
                                </div>
                                <div class="flex justify-between" x-show="ghanapostCode">
                                    <dt class="text-brand-gray-500">{{ __('GhanaPost GPS code') }}</dt>
                                    <dd x-text="ghanapostCode"></dd>
                                </div>
                                <div class="flex justify-between">
                                    <dt class="text-brand-gray-500">{{ __('Location') }}</dt>
                                    <dd x-text="estimatedFee !== null ? '{{ __('Captured') }}' : '{{ __('Not captured, fee calculated on arrival') }}'"></dd>
                                </div>
                            </div>
                        </template>

                        <div class="flex justify-between" x-show="instructions">
                            <dt class="text-brand-gray-500">{{ __('Instructions') }}</dt>
                            <dd x-text="instructions"></dd>
                        </div>

                        <div class="flex justify-between">
                            <dt class="text-brand-gray-500">{{ __('Payment method') }}</dt>
                            <dd x-text="paymentMethod === 'paystack' ? '{{ __('Pay now (card / mobile money)') }}' : '{{ __('Cash on delivery / pickup') }}'"></dd>
                        </div>
                    </dl>

                    <div class="grid grid-cols-2 gap-3">
                        <button
                            type="button" @click="reviewing = false"
                            class="px-4 py-2.5 border border-brand-gray-300 text-sm font-semibold rounded-md hover:bg-brand-gray-100"
                        >
                            {{ __('Edit order') }}
                        </button>
                        <button
                            type="button" @click="$refs.form.requestSubmit()"
                            class="px-4 py-2.5 bg-brand-yellow text-brand-black text-sm font-semibold rounded-md hover:bg-brand-yellow-dark"
                        >
                            {{ __('Confirm & place order') }}
                        </button>
                    </div>
                </div>

                <button
                    type="button" x-show="!reviewing" @click="startReview()" :disabled="!formValid"
                    :class="formValid ? 'bg-brand-yellow text-brand-black hover:bg-brand-yellow-dark cursor-pointer' : 'bg-brand-gray-100 text-brand-gray-400 cursor-not-allowed'"
                    class="w-full px-6 py-3 font-semibold rounded-md transition"
                >
                    {{ __('Review order') }}
                </button>
            </form>
        </div>

        <div>
            <h2 class="font-semibold mb-3">{{ __('Order summary') }}</h2>
            <p class="text-sm text-brand-gray-500 mb-3">{{ $branch->name }}</p>
            <ul class="text-sm space-y-3 mb-4">
                @foreach ($lines as $line)
                    <li>
                        <div class="flex items-center gap-3">
                            @if ($line['image_url'])
                                <img src="{{ $line['image_url'] }}" alt="{{ $line['name_snapshot'] }}" class="w-12 h-12 rounded-md object-cover bg-brand-gray-100 shrink-0">
                            @else
                                <div class="w-12 h-12 rounded-md bg-brand-gray-100 flex items-center justify-center shrink-0" role="img" aria-label="{{ $line['name_snapshot'] }}">
                                    <svg viewBox="0 0 24 24" fill="none" class="w-5 h-5 text-brand-gray-300">
                                        <path d="M4 7a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V7Z" stroke="currentColor" stroke-width="1.5" />
                                        <circle cx="9" cy="10.5" r="1.5" stroke="currentColor" stroke-width="1.5" />
                                        <path d="m5 16 4.5-4 3 2.5L16 11l3 3.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                                    </svg>
                                </div>
                            @endif
                            <div class="flex-1 flex justify-between">
                                <span>{{ $line['quantity'] }}x {{ $line['name_snapshot'] }}</span>
                                <span>GH₵{{ number_format($line['line_total'] / 100, 2) }}</span>
                            </div>
                        </div>
                        @foreach ($line['options'] as $option)
                            <div class="flex justify-between text-brand-gray-500 pl-[60px]">
                                <span>{{ $option['name_snapshot'] }}</span>
                                <span>+GH₵{{ number_format($option['price_delta_snapshot'] / 100, 2) }}</span>
                            </div>
                        @endforeach
                    </li>
                @endforeach
            </ul>
            <div class="flex justify-between">
                <span>{{ __('Subtotal') }}</span>
                <span>GH₵{{ number_format($subtotal / 100, 2) }}</span>
            </div>

            <div class="flex justify-between text-sm mt-2" x-show="discountAmount > 0" x-cloak>
                <span>{{ __('Discount') }}</span>
                <span x-text="'-GH₵' + (discountAmount / 100).toFixed(2)"></span>
            </div>

            @if ($deliveryAvailable)
                <div class="flex justify-between text-sm mt-2" x-show="fulfilmentType === 'delivery' && estimatedFee !== null" x-cloak>
                    <span>{{ __('Delivery fee') }}</span>
                    <span x-text="'GH₵' + (estimatedFee / 100).toFixed(2)"></span>
                </div>
            @endif

            <div class="flex justify-between font-semibold border-t border-brand-gray-100 pt-3 mt-2">
                <span>{{ __('Total') }}</span>
                <span x-text="'GH₵' + (estimatedTotal() / 100).toFixed(2)"></span>
            </div>

            @if ($deliveryAvailable)
                <p x-show="fulfilmentType === 'delivery' && estimatedFee === null" class="text-xs text-brand-gray-500 mt-2">
                    {{ __('Delivery fee will be calculated upon rider arrival.') }}
                </p>
            @endif

            <div class="border-t border-brand-gray-100 pt-3 mt-3">
                <label class="block text-sm font-medium mb-1" for="discount_code">{{ __('Discount code') }}</label>
                <div class="flex gap-2">
                    <input
                        type="text" id="discount_code" name="promo_code" form="checkout-form"
                        x-model="discountCode"
                        placeholder="{{ __('Enter code') }}"
                        class="w-full rounded-md {{ $errors->has('promo_code') ? 'border-brand-red ring-1 ring-brand-red' : 'border-brand-gray-300' }}"
                    >
                    <button
                        type="button" @click="applyPromoCode()"
                        class="px-4 py-2 bg-brand-gray-100 text-brand-black text-sm font-semibold rounded-md hover:bg-brand-gray-300 shrink-0"
                    >{{ __('Apply') }}</button>
                </div>
                <p x-show="promoMessage" x-cloak class="text-xs mt-1" :class="discountAmount > 0 ? 'text-green-600' : 'text-brand-red'" x-text="promoMessage"></p>
                @error('promo_code') <p class="text-sm text-brand-red mt-1">{{ $message }}</p> @enderror
            </div>
        </div>
    </div>

    @include('partials.phone-input-script')
</x-customer-layout>
