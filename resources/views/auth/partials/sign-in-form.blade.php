{{--
    The Express POS sign-in form (staff and rider), styled after the
    sign-in screen in the logo system: large fields on paper, a Midnight
    button with Kente Gold text. Email/phone + password for now; staff ID
    and PIN come with tills per cashier (MVP step 7).

    The business's own logo and name head the form, so staff know which
    install they're signing in to; Express POS is on the dark panel.

    $action, $subheading, $loginName, $loginLabel, $loginType
--}}
@php
    $business = app(\App\Services\Settings\BusinessProfile::class);
    $field = 'mt-1.5 block w-full h-12 rounded-lg border-xpos-line bg-white px-4 text-base text-xpos-midnight placeholder:text-xpos-muted focus:border-xpos-midnight focus:ring-2 focus:ring-xpos-gold';
@endphp

@if ($business->logoUrl())
    <img src="{{ $business->logoUrl() }}" alt="" class="h-16 w-auto max-w-full mb-4">
@endif
<h1 class="text-3xl font-extrabold tracking-tight">{{ $business->name() }}</h1>
<p class="mt-1 text-gray-600">{{ $subheading }}</p>

<x-auth-session-status class="mt-6" :status="session('status')" />

<form method="POST" action="{{ $action }}" class="mt-8 space-y-5">
    @csrf

    <div>
        <label for="{{ $loginName }}" class="block text-sm font-semibold">{{ $loginLabel }}</label>
        <input id="{{ $loginName }}" name="{{ $loginName }}" type="{{ $loginType }}" value="{{ old($loginName) }}" required autofocus autocomplete="username" class="{{ $field }}">
        <x-input-error :messages="$errors->get($loginName)" class="mt-2" />
    </div>

    <div>
        <label for="password" class="block text-sm font-semibold">{{ __('Password') }}</label>
        <div class="relative" x-data="{ show: false }">
            <input id="password" name="password" type="password" x-ref="passwordField" required autocomplete="current-password" class="{{ $field }} pr-12">
            <button
                type="button"
                @click="show = !show; $refs.passwordField.type = show ? 'text' : 'password'"
                class="absolute inset-y-0 right-0 mt-1.5 flex items-center px-4 text-gray-500 hover:text-xpos-midnight"
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
        <x-input-error :messages="$errors->get('password')" class="mt-2" />
    </div>

    <label for="remember_me" class="inline-flex items-center gap-2 text-sm text-gray-700">
        <input id="remember_me" type="checkbox" name="remember" class="rounded border-xpos-line text-xpos-midnight focus:ring-xpos-gold">
        {{ __('Remember me') }}
    </label>

    <button type="submit" class="w-full h-12 rounded-lg bg-xpos-midnight text-xpos-gold font-bold text-base hover:bg-black focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-offset-xpos-paper focus:ring-xpos-gold">
        {{ __('Sign in') }}
    </button>

    @if (Route::has('password.request'))
        <p class="text-center">
            <a href="{{ route('password.request') }}" class="text-sm font-semibold text-xpos-green-dark underline hover:text-xpos-midnight">{{ __('Forgot your password?') }}</a>
        </p>
    @endif
</form>
