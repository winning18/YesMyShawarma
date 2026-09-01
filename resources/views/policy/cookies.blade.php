<x-customer-layout title="Cookie Policy · {{ config('app.name') }}">
    <x-slot name="pageHeader">{{ __('Cookie Policy') }}</x-slot>

    <div class="max-w-3xl mx-auto text-brand-black">
        <p class="text-sm text-brand-gray-500 mb-8">{{ __('Last updated: 2 September 2026') }}</p>

        <p class="leading-relaxed mb-4">
            {{ __('At Yes! My Shawarma, we care about your privacy and want to be open about the technologies we use. This Cookie Policy explains what cookies are, how we use them, and the choices you have.') }}
        </p>

        <h2 class="text-lg font-bold mt-8 mb-3">{{ __('1. What are cookies?') }}</h2>
        <p class="leading-relaxed mb-4">
            {{ __("Cookies are small text files placed on your device — your computer, tablet, or phone — when you visit a website. They're widely used to help websites work properly and to give site owners useful information. Cookies let us recognise your device and remember things like your preferences and past actions, so your next visit is smoother.") }}
        </p>

        <h2 class="text-lg font-bold mt-8 mb-3">{{ __('2. How we use cookies') }}</h2>
        <p class="leading-relaxed mb-2">{{ __('We use cookies to improve your browsing experience and make our services better. The cookies we use fall into these categories:') }}</p>
        <ol class="list-decimal pl-5 space-y-2 leading-relaxed mb-4">
            <li><strong>{{ __('Essential cookies.') }}</strong> {{ __("These are needed for our website to work. They help you move around the site and use key features such as secure areas and your cart. Without them, parts of the site simply won't function.") }}</li>
            <li><strong>{{ __('Performance cookies.') }}</strong> {{ __('These tell us how visitors use our site — which pages are popular, where people run into trouble — so we can keep improving how it works.') }}</li>
            <li><strong>{{ __('Functional cookies.') }}</strong> {{ __('These remember your choices, such as your language or region, so we can give you a more personal experience.') }}</li>
            <li><strong>{{ __('Targeting cookies.') }}</strong> {{ __('These help us show you offers and adverts that are relevant to you, limit how often you see the same ad, and measure how well our campaigns perform.') }}</li>
        </ol>

        <h2 class="text-lg font-bold mt-8 mb-3">{{ __('3. Third-party cookies') }}</h2>
        <p class="leading-relaxed mb-4">
            {{ __('Some cookies are set by trusted third parties acting on our behalf. For example, we may use analytics tools such as Google Analytics to understand how our site is used, and our payment provider (Paystack) may set cookies to process your payment securely. These third parties may use cookies to collect information about your activity across different websites. Their use of your information is governed by their own privacy and cookie policies.') }}
        </p>

        <h2 class="text-lg font-bold mt-8 mb-3">{{ __('4. Your consent and choices') }}</h2>
        <p class="leading-relaxed mb-4">
            {{ __("When you first visit our website, we'll ask for your consent to use non-essential cookies (performance, functional, and targeting). You can accept, decline, or adjust your choices at any time. Essential cookies don't require consent, because the site can't run without them.") }}
        </p>

        <h2 class="text-lg font-bold mt-8 mb-3">{{ __('5. Managing cookies') }}</h2>
        <p class="leading-relaxed mb-4">
            {{ __('You can also control cookies through your browser settings, where most browsers let you view, manage, delete, or block cookies for any website. Keep in mind that if you block or delete certain cookies, some features of our site may not work as they should.') }}
        </p>

        <h2 class="text-lg font-bold mt-8 mb-3">{{ __('6. Changes to this Cookie Policy') }}</h2>
        <p class="leading-relaxed mb-4">
            {{ __('We may update this Cookie Policy from time to time to reflect changes in our practices or the law. When we do, we\'ll revise the "Last updated" date at the top of this page.') }}
        </p>

        <h2 class="text-lg font-bold mt-8 mb-3">{{ __('7. Contact us') }}</h2>
        <p class="leading-relaxed mb-4">
            {{ __('If you have any questions about how we use cookies, please contact us at') }}
            <a href="mailto:info@yesmyshawarma.com" class="underline hover:text-brand-yellow-dark">info@yesmyshawarma.com</a>
            {{ __('or on') }}
            <a href="tel:+233243635265" class="underline hover:text-brand-yellow-dark">024 363 5265</a>.
        </p>
    </div>
</x-customer-layout>
