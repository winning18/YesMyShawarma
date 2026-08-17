<x-customer-layout title="Login · {{ config('app.name') }}">
    <div class="max-w-sm mx-auto">
        <h1 class="text-2xl font-bold mb-6">{{ __('Log in') }}</h1>

        @if ($errors->any())
            <div class="mb-6 rounded-lg bg-brand-red-light border border-brand-red text-brand-red-dark text-sm px-4 py-2">
                {{ $errors->first() }}
            </div>
        @endif

        <form
            method="POST" action="{{ route('customer.login') }}" class="space-y-4"
            x-data="{
                password: '',
                phone: phoneField(@js(old('phone', ''))),
                get formValid() {
                    return this.phone.valid && this.password !== '';
                },
            }"
            @submit="if (! formValid) $event.preventDefault()"
        >
            @csrf

            <div>
                <label class="block text-sm font-medium mb-1">{{ __('Phone number') }} <span class="text-brand-red">*</span></label>
                <input
                    type="tel" name="phone" inputmode="numeric" autocomplete="tel" required autofocus
                    :value="phone.formatted" @input="phone.onInput($event)" @blur="phone.onBlur()"
                    placeholder="024-123-4567" maxlength="12"
                    class="w-full rounded-md"
                    :class="phone.invalid ? 'border-brand-red ring-1 ring-brand-red' : 'border-brand-gray-300'"
                >
                <p class="text-xs text-brand-red mt-1" x-show="phone.invalid">{{ __('Enter a 10-digit phone number.') }}</p>
            </div>

            <div>
                <label class="block text-sm font-medium mb-1">{{ __('Password') }} <span class="text-brand-red">*</span></label>
                <input type="password" name="password" required x-model="password" class="w-full rounded-md border-brand-gray-300">
            </div>

            <label class="flex items-center gap-2 text-sm">
                <input type="checkbox" name="remember">
                {{ __('Remember me') }}
            </label>

            <button
                type="submit" :disabled="!formValid"
                :class="formValid ? 'bg-brand-yellow text-brand-black hover:bg-brand-yellow-dark cursor-pointer' : 'bg-brand-gray-100 text-brand-gray-400 cursor-not-allowed'"
                class="w-full px-6 py-3 font-semibold rounded-md transition"
            >
                {{ __('Log in') }}
            </button>
        </form>

        <p class="text-sm text-brand-gray-500 mt-6">
            {{ __("Don't have an account?") }}
            <a href="{{ route('customer.register') }}" class="underline">{{ __('Sign up') }}</a>
        </p>
    </div>

    @include('partials.phone-input-script')
</x-customer-layout>
