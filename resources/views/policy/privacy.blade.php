<x-customer-layout title="Privacy Policy · {{ config('app.name') }}">
    <x-slot name="pageHeader">{{ __('Privacy Policy') }}</x-slot>

    <div class="max-w-3xl mx-auto text-brand-black">
        <p class="text-sm text-brand-gray-500 mb-8">{{ __('Last updated: :date', ['date' => now()->format('j F Y')]) }}</p>

        <p class="leading-relaxed mb-4">
            {{ __(':name respects your privacy. This page explains what information we collect when you use our site, why we collect it, and how it\'s handled.', ['name' => config('app.name')]) }}
        </p>

        <h2 class="text-lg font-bold mt-8 mb-3">{{ __('Information we collect') }}</h2>
        <ul class="list-disc pl-5 space-y-1 leading-relaxed mb-4">
            <li>{{ __('Your phone number — needed to place any order and to identify you if you order again.') }}</li>
            <li>{{ __('Your name and email, if you give them — used for your account, receipts, and (only if you opt in) our newsletter.') }}</li>
            <li>{{ __('Your delivery address details for a delivery order — the landmark and GhanaPost GPS code your rider needs. If your device shares a precise location at checkout, that\'s used only by your rider for that delivery; our staff and managers only ever see the written address, never the raw coordinates.') }}</li>
            <li>{{ __('Your order history, so you and our staff can look back on past orders.') }}</li>
            <li>{{ __('Messages you send us through the contact form.') }}</li>
        </ul>
        <p class="leading-relaxed mb-4">
            {{ __("We never see or store your full card details — card and Mobile Money payments are handled directly by Paystack, our payment processor.") }}
        </p>

        <h2 class="text-lg font-bold mt-8 mb-3">{{ __('How we use it') }}</h2>
        <p class="leading-relaxed mb-4">
            {{ __('To take, prepare, and deliver your orders; to contact you about an order in progress; to reply to messages you send us; to send you marketing updates if — and only if — you\'ve opted into them; and to understand, in aggregate, how the site is used so we can improve it.') }}
        </p>

        <h2 class="text-lg font-bold mt-8 mb-3">{{ __('Cookies') }}</h2>
        <p class="leading-relaxed mb-4">
            {{ __('We use a small number of cookies to run the site and understand visits — see our') }}
            <a href="{{ route('policy.cookies') }}" class="underline hover:text-brand-yellow-dark">{{ __('Cookie Policy') }}</a>
            {{ __('for details.') }}
        </p>

        <h2 class="text-lg font-bold mt-8 mb-3">{{ __('Who we share it with') }}</h2>
        <ul class="list-disc pl-5 space-y-1 leading-relaxed mb-4">
            <li>{{ __('Paystack, to process an online payment.') }}</li>
            <li>{{ __('Our own delivery riders, who see the address and phone number for the specific order they\'re delivering — nothing else.') }}</li>
            <li>{{ __('An SMS provider, only to send you updates about your own order.') }}</li>
        </ul>
        <p class="leading-relaxed mb-4">
            {{ __('We never sell your information to anyone.') }}
        </p>

        <h2 class="text-lg font-bold mt-8 mb-3">{{ __('Your data, your choices') }}</h2>
        <p class="leading-relaxed mb-4">
            {{ __('You can ask us to see, correct, or delete the information we hold about you at any time — just get in touch. If you check out as a guest and later create an account with the same phone number, your past orders are linked automatically rather than kept as two separate records.') }}
        </p>

        <h2 class="text-lg font-bold mt-8 mb-3">{{ __('Keeping it safe') }}</h2>
        <p class="leading-relaxed mb-4">
            {{ __('We use reasonable technical and organisational measures to protect your information, including encrypted connections (HTTPS) across the site.') }}
        </p>

        <h2 class="text-lg font-bold mt-8 mb-3">{{ __('Children') }}</h2>
        <p class="leading-relaxed mb-4">
            {{ __('Our site is not directed at children, and we don\'t knowingly collect information from them.') }}
        </p>

        <h2 class="text-lg font-bold mt-8 mb-3">{{ __('Changes to this policy') }}</h2>
        <p class="leading-relaxed mb-4">
            {{ __('We may update this policy from time to time; the date at the top of this page will always reflect the latest version.') }}
        </p>

        <h2 class="text-lg font-bold mt-8 mb-3">{{ __('Questions') }}</h2>
        <p class="leading-relaxed mb-4">
            {{ __('Reach us anytime through our') }}
            <a href="{{ route('contact') }}" class="underline hover:text-brand-yellow-dark">{{ __('Contact us') }}</a>
            {{ __('page.') }}
        </p>
    </div>
</x-customer-layout>
