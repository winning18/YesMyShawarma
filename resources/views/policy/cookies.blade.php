<x-customer-layout title="Cookie Policy · {{ config('app.name') }}">
    <x-slot name="pageHeader">{{ __('Cookie Policy') }}</x-slot>

    <div class="max-w-3xl mx-auto text-brand-black">
        <p class="text-sm text-brand-gray-500 mb-8">{{ __('Last updated: :date', ['date' => now()->format('j F Y')]) }}</p>

        <p class="leading-relaxed mb-4">
            {{ __('A cookie is a small file a website stores on your device. We keep our use of them to what actually makes the site work.') }}
        </p>

        <h2 class="text-lg font-bold mt-8 mb-3">{{ __('Cookies we use') }}</h2>
        <ul class="list-disc pl-5 space-y-2 leading-relaxed mb-4">
            <li>
                <span class="font-semibold">{{ __('Session cookie (essential).') }}</span>
                {{ __('Keeps your cart, selected branch, and login state as you move around the site. Without it, checkout won\'t work.') }}
            </li>
            <li>
                <span class="font-semibold">{{ __('Visitor cookie (analytics).') }}</span>
                {{ __("A first-party cookie, kept for up to a year, that carries no personal information — just a random token so we can count visits and see how many visitors go on to order. It's never used to identify you personally or shared with anyone outside our own reporting.") }}
            </li>
        </ul>

        <h2 class="text-lg font-bold mt-8 mb-3">{{ __('Paystack') }}</h2>
        <p class="leading-relaxed mb-4">
            {{ __("If you pay by card or Mobile Money, you're taken to Paystack's own secure payment page to complete it. That page is run by Paystack and is covered by their own cookie and privacy policy, not ours.") }}
        </p>

        <h2 class="text-lg font-bold mt-8 mb-3">{{ __('Managing cookies') }}</h2>
        <p class="leading-relaxed mb-4">
            {{ __('Most browsers let you view, block, or delete cookies from their settings. Blocking the session cookie will stop the cart and checkout from working properly.') }}
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
