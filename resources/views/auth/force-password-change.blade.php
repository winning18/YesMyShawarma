<x-guest-layout>
    <p class="text-sm text-gray-600 mb-4">
        {{ __('Set your own password to continue. This replaces the one-time password you were given.') }}
    </p>

    <form method="POST" action="{{ route('password.force-change.update') }}">
        @csrf
        @method('PUT')

        <div>
            <x-input-label for="current_password" :value="__('One-time password')" required />
            <x-text-input id="current_password" class="block mt-1 w-full" type="password" name="current_password" required autofocus autocomplete="current-password" />
            <x-input-error :messages="$errors->get('current_password')" class="mt-2" />
        </div>

        <div class="mt-4">
            <x-input-label for="password" :value="__('New password')" required />
            <x-text-input id="password" class="block mt-1 w-full" type="password" name="password" required autocomplete="new-password" />
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <div class="mt-4">
            <x-input-label for="password_confirmation" :value="__('Confirm new password')" required />
            <x-text-input id="password_confirmation" class="block mt-1 w-full" type="password" name="password_confirmation" required autocomplete="new-password" />
            <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
        </div>

        <div class="flex items-center justify-end mt-4">
            <x-primary-button>{{ __('Set password') }}</x-primary-button>
        </div>
    </form>
</x-guest-layout>
