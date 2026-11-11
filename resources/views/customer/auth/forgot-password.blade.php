<x-customer-layout title="Forgot password · {{ config('app.name') }}">
    <div class="max-w-sm mx-auto">
        <h1 class="text-2xl font-bold mb-2">{{ __('Forgot your password?') }}</h1>
        <p class="text-sm text-brand-gray-500 mb-6">{{ __('Enter the phone number you log in with. We\'ll text you a code to set a new password.') }}</p>

        @if ($errors->any())
            <div class="mb-6 rounded-lg bg-brand-red-light border border-brand-red text-brand-red-dark text-sm px-4 py-2">{{ $errors->first() }}</div>
        @endif

        <form method="POST" action="{{ route('customer.password.code') }}" class="space-y-4"
            x-data="{ phone: phoneField(@js(old('phone', ''))) }" @submit="if (! phone.valid) $event.preventDefault()">
            @csrf
            <div>
                <label class="block text-sm font-medium mb-1">{{ __('Phone number') }} <span class="text-brand-red">*</span></label>
                <input type="tel" name="phone" inputmode="numeric" autocomplete="tel" required autofocus
                    :value="phone.formatted" @input="phone.onInput($event)" @blur="phone.onBlur()"
                    placeholder="024-123-4567" maxlength="12"
                    class="w-full rounded-md" :class="phone.invalid ? 'border-brand-red ring-1 ring-brand-red' : 'border-brand-gray-300'">
                <p class="text-xs text-brand-red mt-1" x-show="phone.invalid">{{ __('Enter a 10-digit phone number.') }}</p>
            </div>
            <button type="submit" class="w-full px-6 py-3 bg-brand-yellow text-brand-black font-semibold rounded-md hover:bg-brand-yellow-dark">{{ __('Text me a code') }}</button>
        </form>

        <p class="text-sm text-brand-gray-500 mt-6"><a href="{{ route('customer.login') }}" class="underline">{{ __('Back to log in') }}</a></p>
    </div>

    @include('partials.phone-input-script')
</x-customer-layout>
