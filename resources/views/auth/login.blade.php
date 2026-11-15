<x-guest-layout>
    @if ($counter)
        {{-- A counter opens on Staff ID + PIN; managers switch to email. --}}
        <div x-data="{ email: @js($errors->has('email') || old('email') !== null) }">
            <div x-show="! email">
                @include('auth.partials.pin-form', ['counter' => $counter])
                <p class="mt-6 text-center">
                    <button type="button" @click="email = true" class="text-sm font-semibold text-xpos-green-dark underline hover:text-xpos-midnight">{{ __('Managers: sign in with email') }}</button>
                </p>
            </div>
            <div x-show="email" x-cloak>
                @include('auth.partials.sign-in-form', [
                    'action' => route('login'),
                    'subheading' => __('Sign in with your staff email and password.'),
                    'loginName' => 'email',
                    'loginLabel' => __('Email'),
                    'loginType' => 'email',
                ])
                <p class="mt-6 text-center">
                    <button type="button" @click="email = false" class="text-sm font-semibold text-xpos-green-dark underline hover:text-xpos-midnight">{{ __('Sign in with Staff ID and PIN') }}</button>
                </p>
            </div>
        </div>
    @else
        @include('auth.partials.sign-in-form', [
            'action' => route('login'),
            'subheading' => __('Sign in with your staff email and password.'),
            'loginName' => 'email',
            'loginLabel' => __('Email'),
            'loginType' => 'email',
        ])
    @endif
</x-guest-layout>
