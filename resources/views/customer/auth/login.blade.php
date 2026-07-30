<x-customer-layout title="Login · {{ config('app.name') }}">
    <div class="max-w-sm mx-auto">
        <h1 class="text-2xl font-bold mb-6">{{ __('Log in') }}</h1>

        @if ($errors->any())
            <div class="mb-6 rounded-lg bg-brand-red-light border border-brand-red text-brand-red-dark text-sm px-4 py-2">
                {{ $errors->first() }}
            </div>
        @endif

        <form method="POST" action="{{ route('customer.login') }}" class="space-y-4">
            @csrf

            <div>
                <label class="block text-sm font-medium mb-1">{{ __('Phone number') }}</label>
                <input type="tel" name="phone" required value="{{ old('phone') }}" autofocus class="w-full rounded-md border-brand-gray-300">
            </div>

            <div>
                <label class="block text-sm font-medium mb-1">{{ __('Password') }}</label>
                <input type="password" name="password" required class="w-full rounded-md border-brand-gray-300">
            </div>

            <label class="flex items-center gap-2 text-sm">
                <input type="checkbox" name="remember">
                {{ __('Remember me') }}
            </label>

            <button type="submit" class="w-full px-6 py-3 bg-brand-yellow text-brand-black font-semibold rounded-md hover:bg-brand-yellow-dark">
                {{ __('Log in') }}
            </button>
        </form>

        <p class="text-sm text-brand-gray-500 mt-6">
            {{ __("Don't have an account?") }}
            <a href="{{ route('customer.register') }}" class="underline">{{ __('Sign up') }}</a>
        </p>
    </div>
</x-customer-layout>
