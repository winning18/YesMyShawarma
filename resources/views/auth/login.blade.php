<x-guest-layout>
    @include('auth.partials.sign-in-form', [
        'action' => route('login'),
        'subheading' => __('Sign in with your staff email and password.'),
        'loginName' => 'email',
        'loginLabel' => __('Email'),
        'loginType' => 'email',
    ])
</x-guest-layout>
