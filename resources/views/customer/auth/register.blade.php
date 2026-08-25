<x-customer-layout title="Sign up · {{ config('app.name') }}">
    <div class="max-w-sm mx-auto">
        <h1 class="text-2xl font-bold mb-6">{{ __('Sign up') }}</h1>

        @if ($errors->any())
            <div class="mb-6 rounded-lg bg-brand-red-light border border-brand-red text-brand-red-dark text-sm px-4 py-2">
                {{ $errors->first() }}
            </div>
        @endif

        <form
            method="POST" action="{{ route('customer.register') }}" class="space-y-4"
            x-data="{
                name: @js(old('name', '')),
                password: '',
                passwordConfirmation: '',
                phone: phoneField(@js(old('phone', ''))),
                get formValid() {
                    return this.name.trim() !== ''
                        && this.phone.valid
                        && this.password.length >= 6
                        && this.passwordConfirmation === this.password
                        && this.passwordConfirmation !== '';
                },
            }"
            @submit="if (! formValid) $event.preventDefault()"
        >
            @csrf

            <div>
                <label class="block text-sm font-medium mb-1">{{ __('Name') }} <span class="text-brand-red">*</span></label>
                <input type="text" name="name" required x-model="name" autofocus class="w-full rounded-md border-brand-gray-300">
            </div>

            <div>
                <label class="block text-sm font-medium mb-1">{{ __('Phone number') }} <span class="text-brand-red">*</span></label>
                <input
                    type="tel" name="phone" inputmode="numeric" autocomplete="tel" required
                    :value="phone.formatted" @input="phone.onInput($event)" @blur="phone.onBlur()"
                    placeholder="024-123-4567" maxlength="12"
                    class="w-full rounded-md"
                    :class="phone.invalid ? 'border-brand-red ring-1 ring-brand-red' : 'border-brand-gray-300'"
                >
                <p class="text-xs mt-1" :class="phone.invalid ? 'text-brand-red' : 'text-brand-gray-500'">
                    <span x-show="!phone.invalid">{{ __('If you\'ve ordered with us before, this links to your past orders.') }}</span>
                    <span x-show="phone.invalid">{{ __('Enter a 10-digit phone number.') }}</span>
                </p>
            </div>

            <div>
                <label class="block text-sm font-medium mb-1">{{ __('Password') }} <span class="text-brand-red">*</span></label>
                <input type="password" name="password" required x-model="password" class="w-full rounded-md border-brand-gray-300">
            </div>

            <div>
                <label class="block text-sm font-medium mb-1">{{ __('Confirm password') }} <span class="text-brand-red">*</span></label>
                <input type="password" name="password_confirmation" required x-model="passwordConfirmation" class="w-full rounded-md border-brand-gray-300">
            </div>

            <button
                type="submit" :disabled="!formValid"
                :class="formValid ? 'bg-brand-yellow text-brand-black hover:bg-brand-yellow-dark cursor-pointer' : 'bg-brand-gray-100 text-brand-gray-400 cursor-not-allowed'"
                class="w-full px-6 py-3 font-semibold rounded-md transition"
            >
                {{ __('Sign up') }}
            </button>
        </form>

        <p class="text-sm text-brand-gray-500 mt-6">
            {{ __('Already have an account?') }}
            <a href="{{ route('customer.login') }}" class="underline">{{ __('Log in') }}</a>
        </p>
    </div>

    @include('partials.phone-input-script')
</x-customer-layout>
