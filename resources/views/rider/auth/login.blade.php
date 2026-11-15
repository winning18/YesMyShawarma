<x-guest-layout>
    @include('auth.partials.sign-in-form', [
        'action' => route('rider.login'),
        'heading' => __('Rider sign-in'),
        'subheading' => __('Sign in to see your deliveries.'),
        'loginName' => 'login',
        'loginLabel' => __('Email or phone number'),
        'loginType' => 'text',
    ])
</x-guest-layout>
