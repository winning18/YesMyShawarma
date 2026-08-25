<x-customer-layout :title="'Payment not completed · '.config('app.name')">
    <div class="max-w-xl mx-auto text-center">
        <div class="w-16 h-16 mx-auto rounded-full bg-brand-red-light flex items-center justify-center">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-8 h-8 text-brand-red" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
                <path d="M18 6 6 18M6 6l12 12" />
            </svg>
        </div>

        <h1 class="text-2xl font-bold mt-4">{{ __('Payment not completed') }}</h1>
        <p class="text-brand-gray-500 mt-1">
            {{ __("We couldn't confirm your payment for this order — you have not been charged, and nothing has been sent to the kitchen.") }}
        </p>

        <p class="text-sm text-brand-gray-500 mt-4">{{ __('Order reference') }}</p>
        <p class="text-lg font-semibold">{{ $order->reference }}</p>
    </div>

    {{-- The cart was already cleared when this order was created (see
         CheckoutController::store()), so "try again" can't resume it —
         the menu is genuinely the right next step, not a shortcut. --}}
    <div class="max-w-xl mx-auto mt-8 flex flex-col sm:flex-row gap-3">
        <a
            href="{{ route('menu.index') }}"
            class="flex-1 text-center px-6 py-3 bg-brand-yellow text-brand-black font-semibold rounded-md hover:bg-brand-yellow-dark"
        >
            {{ __('Order again') }}
        </a>
        <a
            href="{{ route('contact') }}"
            class="flex-1 text-center px-6 py-3 border border-brand-gray-300 font-semibold rounded-md hover:bg-brand-gray-100"
        >
            {{ __('Contact us') }}
        </a>
    </div>

    <p class="max-w-xl mx-auto mt-4 text-xs text-brand-gray-500 text-center">
        {{ __("If any amount was deducted from your card or mobile money account, it will be automatically reversed by your provider — get in touch if it doesn't reflect within a few days.") }}
    </p>
</x-customer-layout>
