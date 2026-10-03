<x-guest-layout>
    @if (session('status'))
        {{-- Success state replaces the form entirely — the email was just
             sent, so re-showing the same input invites a confused second
             submission rather than confirming anything actually happened. --}}
        <x-auth-session-status class="mb-4" :status="session('status')" />

        <p class="text-sm text-gray-600">
            {{ __("Didn't get it? Check your spam folder, or") }}
            <a href="{{ route('password.request') }}" class="underline text-gray-600 hover:text-gray-900">{{ __('try another email address') }}</a>.
        </p>

        <div class="flex items-center justify-end mt-4">
            <a href="{{ route('login') }}" class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 focus:bg-gray-700 active:bg-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
                {{ __('Back to login') }}
            </a>
        </div>
    @else
        <div class="mb-4 text-sm text-gray-600">
            {{ __('Forgot your password? No problem. Just let us know your email address and we will email you a password reset link that will allow you to choose a new one.') }}
        </div>

        <form method="POST" action="{{ route('password.email') }}">
            @csrf

            <!-- Email Address -->
            <div>
                <x-input-label for="email" :value="__('Email')" required />
                <x-text-input id="email" class="block mt-1 w-full" type="email" name="email" :value="old('email')" required autofocus />
                <x-input-error :messages="$errors->get('email')" class="mt-2" />
            </div>

            <div class="flex items-center justify-end mt-4">
                <a href="{{ route('login') }}" class="underline text-sm text-gray-600 hover:text-gray-900 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-brand-yellow">
                    {{ __('Back to login') }}
                </a>

                <x-primary-button class="ms-3">
                    {{ __('Email Password Reset Link') }}
                </x-primary-button>
            </div>
        </form>
    @endif
</x-guest-layout>
