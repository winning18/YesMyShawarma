<x-customer-layout title="Set a new password · {{ config('app.name') }}">
    <div class="max-w-sm mx-auto">
        <h1 class="text-2xl font-bold mb-2">{{ __('Set a new password') }}</h1>
        <p class="text-sm text-brand-gray-500 mb-6">{{ __('Enter the 6-digit code we texted to :phone.', ['phone' => $phone]) }}</p>

        @if ($errors->any())
            <div class="mb-6 rounded-lg bg-brand-red-light border border-brand-red text-brand-red-dark text-sm px-4 py-2">{{ $errors->first() }}</div>
        @endif

        <form method="POST" action="{{ route('customer.password.update') }}" class="space-y-4">
            @csrf
            <div>
                <label for="code" class="block text-sm font-medium mb-1">{{ __('Code') }} <span class="text-brand-red">*</span></label>
                <input id="code" type="text" name="code" required inputmode="numeric" autocomplete="one-time-code" maxlength="6" pattern="[0-9]{6}" autofocus
                    class="w-40 rounded-md border-brand-gray-300 tracking-widest text-lg">
            </div>
            <div>
                <label for="password" class="block text-sm font-medium mb-1">{{ __('New password (at least 8 characters)') }} <span class="text-brand-red">*</span></label>
                <input id="password" type="password" name="password" required autocomplete="new-password" class="w-full rounded-md border-brand-gray-300">
            </div>
            <div>
                <label for="password_confirmation" class="block text-sm font-medium mb-1">{{ __('Confirm new password') }} <span class="text-brand-red">*</span></label>
                <input id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password" class="w-full rounded-md border-brand-gray-300">
            </div>
            <button type="submit" class="w-full px-6 py-3 bg-brand-yellow text-brand-black font-semibold rounded-md hover:bg-brand-yellow-dark">{{ __('Set password and log in') }}</button>
        </form>

        <p class="text-sm text-brand-gray-500 mt-6">
            {{ __('No code?') }} <a href="{{ route('customer.password.request') }}" class="underline">{{ __('Send it again') }}</a>
        </p>
    </div>
</x-customer-layout>
