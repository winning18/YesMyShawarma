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
                <div class="relative" x-data="{ show: false }">
                    <input :type="show ? 'text' : 'password'" name="password" required x-model="password" class="w-full rounded-md border-brand-gray-300 pr-10">
                    <button
                        type="button" @click="show = !show"
                        class="absolute inset-y-0 right-0 flex items-center px-3 text-brand-gray-300 hover:text-brand-gray-500"
                        :aria-label="show ? @js(__('Hide password')) : @js(__('Show password'))"
                    >
                        <svg x-show="!show" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="w-5 h-5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                        <svg x-show="show" x-cloak xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="w-5 h-5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88" />
                        </svg>
                    </button>
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium mb-1">{{ __('Confirm password') }} <span class="text-brand-red">*</span></label>
                <div class="relative" x-data="{ show: false }">
                    <input :type="show ? 'text' : 'password'" name="password_confirmation" required x-model="passwordConfirmation" class="w-full rounded-md border-brand-gray-300 pr-10">
                    <button
                        type="button" @click="show = !show"
                        class="absolute inset-y-0 right-0 flex items-center px-3 text-brand-gray-300 hover:text-brand-gray-500"
                        :aria-label="show ? @js(__('Hide password')) : @js(__('Show password'))"
                    >
                        <svg x-show="!show" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="w-5 h-5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                        <svg x-show="show" x-cloak xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="w-5 h-5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88" />
                        </svg>
                    </button>
                </div>
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
