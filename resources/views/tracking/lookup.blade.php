<x-customer-layout title="Track order · {{ config('app.name') }}">
    <h1 class="text-2xl font-bold mb-6">{{ __('Track your order') }}</h1>

    @if ($errors->any())
        <div class="mb-6 rounded-lg bg-brand-red-light border border-brand-red text-brand-red-dark text-sm px-4 py-2">
            {{ $errors->first() }}
        </div>
    @endif

    @if ($orders !== null)
        @if ($orders->isEmpty())
            <p class="text-brand-gray-500">{{ __("You haven't placed any orders yet.") }}</p>
        @else
            <div class="space-y-3">
                @foreach ($orders as $order)
                    <a href="{{ route('tracking.show', $order) }}" class="block border border-brand-gray-100 rounded-lg p-4 hover:border-brand-yellow">
                        <div class="flex justify-between">
                            <span class="font-semibold">{{ $order->reference }}</span>
                            <span class="text-sm text-brand-gray-500">{{ ucfirst(str_replace('_', ' ', $order->status)) }}</span>
                        </div>
                        <p class="text-sm text-brand-gray-500">{{ $order->placed_at?->format('d M Y, g:ia') }}</p>
                    </a>
                @endforeach
            </div>
        @endif
    @else
        <p class="text-sm text-brand-gray-500 mb-4">
            {{ __('Enter the phone number and order reference from your confirmation to find your order.') }}
        </p>

        <form method="POST" action="{{ route('tracking.find') }}" class="space-y-4 max-w-sm">
            @csrf

            <div>
                <label class="block text-sm font-medium mb-1">{{ __('Phone number') }}</label>
                <input type="tel" name="phone" required value="{{ old('phone') }}" class="w-full rounded-md border-brand-gray-300">
            </div>

            <div>
                <label class="block text-sm font-medium mb-1">{{ __('Order reference') }}</label>
                <input type="text" name="reference" required value="{{ old('reference') }}" class="w-full rounded-md border-brand-gray-300">
            </div>

            <button type="submit" class="px-6 py-3 bg-brand-yellow text-brand-black font-semibold rounded-md hover:bg-brand-yellow-dark">
                {{ __('Find my order') }}
            </button>
        </form>

        <p class="text-sm text-brand-gray-500 mt-6">
            {{ __('Have an account?') }}
            <a href="{{ route('customer.login') }}" class="underline">{{ __('Log in') }}</a>
            {{ __('to see your full order history.') }}
        </p>
    @endif
</x-customer-layout>
