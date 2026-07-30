<x-customer-layout title="Sign up · {{ config('app.name') }}">
    <div class="max-w-sm mx-auto">
        <h1 class="text-2xl font-bold mb-6">{{ __('Sign up') }}</h1>

        @if ($errors->any())
            <div class="mb-6 rounded-lg bg-brand-red-light border border-brand-red text-brand-red-dark text-sm px-4 py-2">
                {{ $errors->first() }}
            </div>
        @endif

        <form method="POST" action="{{ route('customer.register') }}" class="space-y-4">
            @csrf

            <div>
                <label class="block text-sm font-medium mb-1">{{ __('Name') }}</label>
                <input type="text" name="name" required value="{{ old('name') }}" autofocus class="w-full rounded-md border-brand-gray-300">
            </div>

            <div>
                <label class="block text-sm font-medium mb-1">{{ __('Phone number') }}</label>
                <input type="tel" name="phone" required value="{{ old('phone') }}" class="w-full rounded-md border-brand-gray-300">
                <p class="text-xs text-brand-gray-500 mt-1">{{ __('If you\'ve ordered with us before, this links to your past orders.') }}</p>
            </div>

            <div>
                <label class="block text-sm font-medium mb-1">{{ __('Password') }}</label>
                <input type="password" name="password" required class="w-full rounded-md border-brand-gray-300">
            </div>

            <div>
                <label class="block text-sm font-medium mb-1">{{ __('Confirm password') }}</label>
                <input type="password" name="password_confirmation" required class="w-full rounded-md border-brand-gray-300">
            </div>

            <button type="submit" class="w-full px-6 py-3 bg-brand-yellow text-brand-black font-semibold rounded-md hover:bg-brand-yellow-dark">
                {{ __('Sign up') }}
            </button>
        </form>

        <p class="text-sm text-brand-gray-500 mt-6">
            {{ __('Already have an account?') }}
            <a href="{{ route('customer.login') }}" class="underline">{{ __('Log in') }}</a>
        </p>
    </div>
</x-customer-layout>
