<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Settings') }}</h2>
    </x-slot>

    <div class="max-w-xl mx-auto py-8 px-4 space-y-4">
        @if (session('status'))
            <div class="rounded-md bg-green-50 text-green-700 text-sm px-4 py-2">{{ session('status') }}</div>
        @endif

        <form method="POST" action="{{ route('dashboard.settings.update') }}" class="bg-white shadow rounded-lg p-6 space-y-6">
            @csrf
            @method('PUT')

            <div>
                <h3 class="font-semibold text-gray-800">{{ __('Order reference numbers') }}</h3>
                <p class="text-sm text-gray-500">
                    {{ __('The prefix shown on every order reference, e.g. :example. A random 6-character code is added automatically.', ['example' => 'YMGS-POS-4F8K2Q']) }}
                </p>
            </div>

            <div>
                <x-input-label for="order_reference_prefix_pos" :value="__('POS orders prefix')" required />
                <x-text-input
                    id="order_reference_prefix_pos" name="order_reference_prefix_pos" type="text"
                    class="mt-1 block w-full uppercase" :value="old('order_reference_prefix_pos', $posPrefix)" required
                    pattern="[A-Za-z0-9-]+" maxlength="20"
                />
                <x-input-error class="mt-2" :messages="$errors->get('order_reference_prefix_pos')" />
            </div>

            <div>
                <x-input-label for="order_reference_prefix_web" :value="__('Web orders prefix')" required />
                <x-text-input
                    id="order_reference_prefix_web" name="order_reference_prefix_web" type="text"
                    class="mt-1 block w-full uppercase" :value="old('order_reference_prefix_web', $webPrefix)" required
                    pattern="[A-Za-z0-9-]+" maxlength="20"
                />
                <x-input-error class="mt-2" :messages="$errors->get('order_reference_prefix_web')" />
            </div>

            <p class="text-xs text-gray-400">
                {{ __('Letters, numbers and dashes only. Only applies to new orders. Existing references are never changed.') }}
            </p>

            <div class="border-t border-gray-100 pt-6">
                <h3 class="font-semibold text-gray-800">{{ __('Paystack') }}</h3>
                <p class="text-sm text-gray-500 mb-3">
                    {{ __('Whether customers can pay online (card / mobile money) at checkout. While this is off, checkout only offers cash — nothing else about the site changes.') }}
                </p>
                <label class="flex items-center gap-2 text-sm text-gray-800">
                    <input type="checkbox" name="paystack_enabled" value="1" {{ old('paystack_enabled', $paystackEnabled) ? 'checked' : '' }} class="rounded border-gray-300">
                    {{ __('Accept Paystack payments at checkout') }}
                </label>
            </div>

            <x-primary-button>{{ __('Save settings') }}</x-primary-button>
        </form>
    </div>
</x-app-layout>
