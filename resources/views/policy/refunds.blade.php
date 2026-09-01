<x-customer-layout title="Refund Policy · {{ config('app.name') }}">
    <x-slot name="pageHeader">{{ __('Refund Policy') }}</x-slot>

    <div class="max-w-3xl mx-auto text-brand-black">
        <p class="text-sm text-brand-gray-500 mb-8">{{ __('Effective date: 2 September 2026') }}</p>

        <p class="leading-relaxed mb-4">
            {{ __('At Yes! My Shawarma, we are committed to serving great food and giving you a great experience every time you order. We know things occasionally go wrong, and when they do, we want to put them right. This policy explains how we handle refunds and how to raise a concern with us.') }}
        </p>

        <h2 class="text-lg font-bold mt-8 mb-3">{{ __('1. Wrong or incomplete orders') }}</h2>
        <p class="leading-relaxed mb-2">{{ __('We work hard to get every order right. If you receive the wrong item, or your order arrives incomplete or incorrect:') }}</p>
        <ul class="list-disc pl-5 space-y-1 leading-relaxed mb-4">
            <li><strong>{{ __('Tell us quickly.') }}</strong> {{ __('Contact our customer service team within 1 hour of delivery, by phone or email.') }}</li>
            <li><strong>{{ __('Share the details.') }}</strong> {{ __('Have your order number ready, tell us exactly what was wrong, and send a photo if it helps.') }}</li>
            <li><strong>{{ __("We'll fix it.") }}</strong> {{ __('Once we\'ve confirmed the issue, we\'ll send you the correct item at no extra cost.') }}</li>
        </ul>

        <h2 class="text-lg font-bold mt-8 mb-3">{{ __('2. Quality concerns') }}</h2>
        <p class="leading-relaxed mb-2">{{ __("We take real pride in the quality of our food. If something isn't up to standard:") }}</p>
        <ul class="list-disc pl-5 space-y-1 leading-relaxed mb-4">
            <li><strong>{{ __('Show us the problem.') }}</strong> {{ __('Take a clear photo of the item, highlighting the issue (for example, undercooked or spoiled).') }}</li>
            <li><strong>{{ __('Get in touch.') }}</strong> {{ __('Contact us within 1 hour of delivery with your order number and photos.') }}</li>
            <li><strong>{{ __("We'll review it.") }}</strong> {{ __("Our team will look into your report. If we agree the quality falls short of our standards, we'll offer a replacement or a full refund, depending on the situation.") }}</li>
        </ul>

        <h2 class="text-lg font-bold mt-8 mb-3">{{ __('3. Delivery delays') }}</h2>
        <p class="leading-relaxed mb-2">{{ __('Getting your food to you on time matters to us. If your order is significantly late:') }}</p>
        <ul class="list-disc pl-5 space-y-1 leading-relaxed mb-4">
            <li><strong>{{ __('Let us know.') }}</strong> {{ __('If your order is more than 60 minutes past the estimated delivery time, please contact our customer service team.') }}</li>
            <li><strong>{{ __("We'll make it right.") }}</strong> {{ __('Depending on the circumstances, we may offer a discount on your next order or a partial refund for the inconvenience.') }}</li>
        </ul>

        <h2 class="text-lg font-bold mt-8 mb-3">{{ __('4. How refunds are processed') }}</h2>
        <ul class="list-disc pl-5 space-y-1 leading-relaxed mb-4">
            <li><strong>{{ __('Processing time.') }}</strong> {{ __("Once a refund is approved, we'll process it back to your original payment method (card or mobile money) within 2 business days. Depending on your bank or mobile money provider, the funds may take a little longer to reflect.") }}</li>
            <li><strong>{{ __('Confirmation.') }}</strong> {{ __("We'll send you a message by SMS or email once your refund has been processed.") }}</li>
        </ul>

        <h2 class="text-lg font-bold mt-8 mb-3">{{ __("5. When refunds don't apply") }}</h2>
        <p class="leading-relaxed mb-2">{{ __("Some situations don't qualify for a refund:") }}</p>
        <ul class="list-disc pl-5 space-y-1 leading-relaxed mb-4">
            <li><strong>{{ __('Reported too late.') }}</strong> {{ __('Orders confirmed as delivered that are not reported within the time frames set out above.') }}</li>
            <li><strong>{{ __('Personal taste.') }}</strong> {{ __("Refunds aren't given for personal preferences, for example a topping you requested but then didn't enjoy.") }}</li>
            <li><strong>{{ __('Third-party orders.') }}</strong> {{ __("If you ordered through a third-party delivery platform rather than directly from us, that platform's own refund policy applies, and refunds must be requested through them.") }}</li>
        </ul>

        <h2 class="text-lg font-bold mt-8 mb-3">{{ __('6. How to reach us') }}</h2>
        <p class="leading-relaxed mb-2">{{ __("We're here to help. To report an issue or request a refund, contact our customer service team:") }}</p>
        <ul class="list-disc pl-5 space-y-1 leading-relaxed mb-4">
            <li>
                {{ __('Phone:') }}
                <a href="tel:+233243635265" class="underline hover:text-brand-yellow-dark">024 363 5265</a>
            </li>
            <li>
                {{ __('Email:') }}
                <a href="mailto:info@yesmyshawarma.com" class="underline hover:text-brand-yellow-dark">info@yesmyshawarma.com</a>
            </li>
            <li>{{ __('WhatsApp: available during business hours.') }}</li>
        </ul>

        <h2 class="text-lg font-bold mt-8 mb-3">{{ __('7. Your satisfaction') }}</h2>
        <p class="leading-relaxed mb-4">
            {{ __('Your satisfaction is what drives us. We take every concern seriously and genuinely welcome your feedback. If you have ideas on how we can serve you better, please tell us.') }}
        </p>

        <h2 class="text-lg font-bold mt-8 mb-3">{{ __('8. Changes to this policy') }}</h2>
        <p class="leading-relaxed mb-4">
            {{ __('We may update this Refund Policy from time to time. Any changes will be posted here with a revised effective date, so we encourage you to check back now and then.') }}
        </p>

        <p class="leading-relaxed mb-4">
            {{ __('Thank you for choosing Yes! My Shawarma. We appreciate your business and are committed to making every order a good one.') }}
        </p>
    </div>
</x-customer-layout>
