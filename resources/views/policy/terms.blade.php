<x-customer-layout title="Terms & Conditions · {{ config('app.name') }}">
    <x-slot name="pageHeader">{{ __('Terms & Conditions') }}</x-slot>

    <div class="max-w-3xl mx-auto text-brand-black">
        <p class="text-sm text-brand-gray-500 mb-8">{{ __('Last updated: 2 September 2026') }}</p>

        <p class="leading-relaxed mb-4">
            {{ __('Welcome to Yes! My Shawarma. These Terms and Conditions ("Terms") govern your use of our website, www.yesmyshawarma.com (the "Site"), and the services we provide through it. By accessing or using the Site, you agree to be bound by these Terms. If you do not agree, please do not use the Site.') }}
        </p>

        <h2 class="text-lg font-bold mt-8 mb-3">{{ __('1. Eligibility and acceptance') }}</h2>
        <p class="leading-relaxed mb-4">
            {{ __('By using the Site, you confirm that you are at least 18 years old, or that you are using it with the permission of a parent or guardian. If you are using the Site on behalf of a business, you confirm that you have the authority to bind that business to these Terms.') }}
        </p>

        <h2 class="text-lg font-bold mt-8 mb-3">{{ __('2. Your account') }}</h2>
        <p class="leading-relaxed mb-4">
            {{ __('To use certain features of the Site, you may need to create an account. You are responsible for keeping your account details confidential and for everything that happens under your account. Please let us know straight away if you notice any unauthorised use of your account.') }}
        </p>

        <h2 class="text-lg font-bold mt-8 mb-3">{{ __('3. Ordering and payment') }}</h2>
        <p class="leading-relaxed mb-4">
            {{ __('All orders you place through the Site are subject to our acceptance, and we may refuse or cancel any order at our discretion. Prices may change without notice. Payment is made at the time you place your order, using the payment methods offered on the Site, including card and mobile money via our secure payment provider.') }}
        </p>

        <h2 class="text-lg font-bold mt-8 mb-3">{{ __('4. Delivery') }}</h2>
        <p class="leading-relaxed mb-4">
            {{ __('Delivery times are estimates and may vary depending on your location and other factors outside our control. While we always aim to deliver on time, we are not liable for delays.') }}
        </p>

        <h2 class="text-lg font-bold mt-8 mb-3">{{ __('5. Refunds and cancellations') }}</h2>
        <p class="leading-relaxed mb-4">
            {{ __('If you wish to cancel an order, please contact us as soon as possible. Refunds are handled in line with our') }}
            <a href="{{ route('policy.refunds') }}" class="underline hover:text-brand-yellow-dark">{{ __('Refund Policy') }}</a>,
            {{ __('which is available on the Site.') }}
        </p>

        <h2 class="text-lg font-bold mt-8 mb-3">{{ __('6. Intellectual property') }}</h2>
        <p class="leading-relaxed mb-4">
            {{ __('All content on the Site — including text, graphics, logos, images, and software — belongs to Yes! My Shawarma or its licensors and is protected by copyright and other intellectual property laws. You may not use any of this content without our express written permission.') }}
        </p>

        <h2 class="text-lg font-bold mt-8 mb-3">{{ __('7. Limitation of liability') }}</h2>
        <p class="leading-relaxed mb-4">
            {{ __('To the fullest extent permitted by law, Yes! My Shawarma shall not be liable for any indirect, incidental, special, or consequential damages arising out of or in connection with your use of the Site or our services.') }}
        </p>

        <h2 class="text-lg font-bold mt-8 mb-3">{{ __('8. Indemnification') }}</h2>
        <p class="leading-relaxed mb-4">
            {{ __('You agree to defend, indemnify, and hold harmless Yes! My Shawarma, its affiliates, and their respective officers, directors, employees, and agents from any claims, damages, liabilities, losses, and expenses arising out of your use of the Site or your breach of these Terms.') }}
        </p>

        <h2 class="text-lg font-bold mt-8 mb-3">{{ __('9. Governing law') }}</h2>
        <p class="leading-relaxed mb-4">
            {{ __('These Terms are governed by and interpreted in accordance with the laws of the Republic of Ghana, without regard to its conflict of law principles. You agree that the courts of Ghana shall have jurisdiction over any dispute arising from these Terms.') }}
        </p>

        <h2 class="text-lg font-bold mt-8 mb-3">{{ __('10. Changes to these Terms') }}</h2>
        <p class="leading-relaxed mb-4">
            {{ __('We may update these Terms at any time. When we do, we\'ll post the revised Terms on this page and update the "Last updated" date above. We encourage you to review these Terms from time to time. Continuing to use the Site after changes are posted means you accept the updated Terms.') }}
        </p>

        <h2 class="text-lg font-bold mt-8 mb-3">{{ __('11. Contact us') }}</h2>
        <p class="leading-relaxed mb-2">{{ __('If you have any questions about these Terms, please contact us:') }}</p>
        <ul class="list-disc pl-5 space-y-1 leading-relaxed mb-4">
            <li>
                {{ __('Email:') }}
                <a href="mailto:info@yesmyshawarma.com" class="underline hover:text-brand-yellow-dark">info@yesmyshawarma.com</a>
            </li>
            <li>
                {{ __('Phone:') }}
                <a href="tel:+233243635265" class="underline hover:text-brand-yellow-dark">024 363 5265</a>
            </li>
        </ul>
    </div>
</x-customer-layout>
