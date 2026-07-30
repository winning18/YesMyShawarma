<x-customer-layout title="Checkout · {{ config('app.name') }}">
    <h1 class="text-2xl font-bold mb-6">{{ __('Checkout') }}</h1>

    @if ($errors->any())
        <div class="mb-6 rounded-lg bg-brand-red-light border border-brand-red text-brand-red-dark text-sm px-4 py-2 space-y-1">
            @foreach ($errors->all() as $message)
                <p>{{ $message }}</p>
            @endforeach
        </div>
    @endif

    <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
        <div class="md:col-span-2">
            <form
                method="POST"
                action="{{ route('checkout.store') }}"
                x-data="{ fulfilmentType: 'pickup' }"
                class="space-y-6"
            >
                @csrf

                <div>
                    <label class="block text-sm font-medium mb-1">{{ __('Name') }}</label>
                    <input
                        type="text" name="name" required
                        value="{{ old('name', $customer->name ?? '') }}"
                        class="w-full rounded-md border-brand-gray-300"
                    >
                </div>

                <div>
                    <label class="block text-sm font-medium mb-1">{{ __('Phone number') }}</label>
                    <input
                        type="tel" name="phone" required
                        value="{{ old('phone', $customer->phone ?? '') }}"
                        class="w-full rounded-md border-brand-gray-300"
                    >
                </div>

                @if ($deliveryAvailable)
                    <div>
                        <label class="block text-sm font-medium mb-2">{{ __('Fulfilment') }}</label>
                        <div class="flex gap-6 text-sm">
                            <label class="flex items-center gap-2">
                                <input type="radio" name="fulfilment_type" value="pickup" x-model="fulfilmentType" checked>
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
                            <label class="block text-sm font-medium mb-1">{{ __('GhanaPost GPS code') }}</label>
                            <input type="text" name="ghanapost_code" value="{{ old('ghanapost_code') }}" class="w-full rounded-md border-brand-gray-300">
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1">{{ __('Landmark') }}</label>
                            <input type="text" name="landmark" value="{{ old('landmark') }}" class="w-full rounded-md border-brand-gray-300">
                        </div>
                        <div x-data="geolocator()">
                            <button type="button" @click="locate()" class="text-sm underline text-brand-red">
                                {{ __('Use my current location') }}
                            </button>
                            <span x-show="status" x-text="status" class="text-sm text-brand-gray-500 ml-2"></span>
                            <input type="hidden" name="lat" x-model="lat" value="{{ old('lat') }}">
                            <input type="hidden" name="lng" x-model="lng" value="{{ old('lng') }}">
                        </div>
                    </div>
                @endif

                <div>
                    <label class="block text-sm font-medium mb-2">{{ __('Payment method') }}</label>
                    <div class="flex gap-6 text-sm">
                        <label class="flex items-center gap-2">
                            <input type="radio" name="payment_method" value="cash" checked>
                            {{ __('Cash on delivery / pickup') }}
                        </label>
                        <label class="flex items-center gap-2">
                            <input type="radio" name="payment_method" value="paystack">
                            {{ __('Pay now (card / mobile money)') }}
                        </label>
                    </div>
                </div>

                <button type="submit" class="w-full px-6 py-3 bg-brand-yellow text-brand-black font-semibold rounded-md hover:bg-brand-yellow-dark">
                    {{ __('Place order') }}
                </button>
            </form>
        </div>

        <div>
            <h2 class="font-semibold mb-3">{{ __('Order summary') }}</h2>
            <p class="text-sm text-brand-gray-500 mb-3">{{ $branch->name }}</p>
            <ul class="text-sm space-y-2 mb-4">
                @foreach ($lines as $line)
                    <li class="flex justify-between">
                        <span>{{ $line['quantity'] }}x {{ $line['name_snapshot'] }}</span>
                        <span>GH₵{{ number_format($line['line_total'] / 100, 2) }}</span>
                    </li>
                @endforeach
            </ul>
            <div class="flex justify-between font-semibold border-t border-brand-gray-100 pt-3">
                <span>{{ __('Subtotal') }}</span>
                <span>GH₵{{ number_format($subtotal / 100, 2) }}</span>
            </div>

            @if ($availableDrinks->isNotEmpty())
                <div class="mt-6 border-t border-brand-gray-100 pt-4">
                    <p class="text-sm font-semibold mb-3">{{ __('Add a drink?') }}</p>
                    <div class="space-y-2">
                        @foreach ($availableDrinks as $drink)
                            <form method="POST" action="{{ route('cart.add') }}" class="flex items-center justify-between text-sm">
                                @csrf
                                <input type="hidden" name="branch_id" value="{{ $branch->id }}">
                                <input type="hidden" name="menu_item_id" value="{{ $drink->id }}">
                                <input type="hidden" name="quantity" value="1">
                                <span>{{ $drink->name }} — GH₵{{ number_format(($drink->pivot->price_override ?? $drink->base_price) / 100, 2) }}</span>
                                <button type="submit" class="text-brand-red hover:text-brand-red-dark font-semibold">+ {{ __('Add') }}</button>
                            </form>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    </div>

    <script>
        function geolocator() {
            return {
                lat: null,
                lng: null,
                status: '',

                locate() {
                    if (!navigator.geolocation) {
                        this.status = '{{ __('Geolocation not supported on this device.') }}';
                        return;
                    }

                    this.status = '{{ __('Locating…') }}';

                    navigator.geolocation.getCurrentPosition(
                        (position) => {
                            this.lat = position.coords.latitude;
                            this.lng = position.coords.longitude;
                            this.status = '{{ __('Location captured.') }}';
                        },
                        () => {
                            this.status = '{{ __('Could not get your location — enter your landmark clearly instead.') }}';
                        }
                    );
                },
            };
        }
    </script>
</x-customer-layout>
