<x-customer-layout title="Terms & Conditions · {{ config('app.name') }}">
    <x-slot name="pageHeader">{{ __('Terms & Conditions') }}</x-slot>

    <div class="max-w-3xl mx-auto text-brand-black">
        <p class="text-sm text-brand-gray-500 mb-8">{{ __('Last updated: :date', ['date' => now()->format('j F Y')]) }}</p>

        <p class="leading-relaxed mb-4">
            {{ __('These terms apply whenever you browse :name or place an order with us, online or at one of our branches. Placing an order means you accept them.', ['name' => config('app.name')]) }}
        </p>

        <h2 class="text-lg font-bold mt-8 mb-3">{{ __('Orders') }}</h2>
        <p class="leading-relaxed mb-4">
            {{ __("You can check out as a guest with just your phone number, or create an account. A branch confirms every order after it's placed — a branch may decline an order (for example, if it's closed, overloaded, or out of stock on an item), in which case any payment already taken is refunded.") }}
        </p>

        <h2 class="text-lg font-bold mt-8 mb-3">{{ __('Pricing and payment') }}</h2>
        <p class="leading-relaxed mb-4">
            {{ __('All prices are in Ghanaian cedis (GHS) and may change without notice. You can pay cash on pickup or delivery, or pay online upfront by card or Mobile Money through Paystack. Where a promo code is offered, it applies only to the order it was used on and only under the terms stated for it.') }}
        </p>

        <h2 class="text-lg font-bold mt-8 mb-3">{{ __('Delivery and pickup') }}</h2>
        <p class="leading-relaxed mb-4">
            {{ __('Pickup is available at any branch during its opening hours. Delivery is available in the areas we currently cover, with the fee calculated by distance from the branch at checkout. An order placed while a branch is closed is prepared once it reopens.') }}
        </p>

        <h2 class="text-lg font-bold mt-8 mb-3">{{ __('Cancellations') }}</h2>
        <p class="leading-relaxed mb-4">
            {{ __("An order can be cancelled before a branch accepts it. Once a branch has accepted and started preparing an order, cancellation is at the branch's discretion — contact the branch directly as soon as possible if something has changed.") }}
        </p>

        <h2 class="text-lg font-bold mt-8 mb-3">{{ __('Your account') }}</h2>
        <p class="leading-relaxed mb-4">
            {{ __('If you set a password on your account, keep your login details to yourself and let us know right away if you think someone else has access to it. Your guest order history carries over automatically once you register with the same phone number.') }}
        </p>

        <h2 class="text-lg font-bold mt-8 mb-3">{{ __('Reviews') }}</h2>
        <p class="leading-relaxed mb-4">
            {{ __("If you leave a review, it should be honest and based on your own order. We review submissions before they're shown publicly and may decline to publish one that's abusive, spam, or clearly not genuine.") }}
        </p>

        <h2 class="text-lg font-bold mt-8 mb-3">{{ __('Liability') }}</h2>
        <p class="leading-relaxed mb-4">
            {{ __('Ask your branch about ingredients if you have an allergy or dietary requirement before ordering. We are not responsible for delays caused by circumstances outside our reasonable control, such as traffic or severe weather.') }}
        </p>

        <h2 class="text-lg font-bold mt-8 mb-3">{{ __('Changes to these terms') }}</h2>
        <p class="leading-relaxed mb-4">
            {{ __('We may update these terms from time to time. Continuing to use the site or place orders after a change means you accept the updated terms.') }}
        </p>

        <h2 class="text-lg font-bold mt-8 mb-3">{{ __('Governing law') }}</h2>
        <p class="leading-relaxed mb-4">
            {{ __('These terms are governed by the laws of Ghana.') }}
        </p>

        <h2 class="text-lg font-bold mt-8 mb-3">{{ __('Questions') }}</h2>
        <p class="leading-relaxed mb-4">
            {{ __('Reach us anytime through our') }}
            <a href="{{ route('contact') }}" class="underline hover:text-brand-yellow-dark">{{ __('Contact us') }}</a>
            {{ __('page.') }}
        </p>
    </div>
</x-customer-layout>
