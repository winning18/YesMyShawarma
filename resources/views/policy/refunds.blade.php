<x-customer-layout title="Refund Policy · {{ config('app.name') }}">
    <x-slot name="pageHeader">{{ __('Refund Policy') }}</x-slot>

    <div class="max-w-3xl mx-auto text-brand-black">
        <p class="text-sm text-brand-gray-500 mb-8">{{ __('Last updated: :date', ['date' => now()->format('j F Y')]) }}</p>

        <p class="leading-relaxed mb-4">
            {{ __('We want every order to be right. If something goes wrong, our branches handle refund requests case by case so the outcome fits what actually happened.') }}
        </p>

        <h2 class="text-lg font-bold mt-8 mb-3">{{ __('When a refund may apply') }}</h2>
        <ul class="list-disc pl-5 space-y-1 leading-relaxed mb-4">
            <li>{{ __('An item was missing, wrong, or not as described.') }}</li>
            <li>{{ __("There was a genuine quality issue with the food.") }}</li>
            <li>{{ __('Your order never arrived or was never available for pickup.') }}</li>
            <li>{{ __('An order was cancelled by the branch, or by you, before it started being prepared.') }}</li>
        </ul>

        <h2 class="text-lg font-bold mt-8 mb-3">{{ __('How to request one') }}</h2>
        <p class="leading-relaxed mb-4">
            {{ __("Contact the branch you ordered from — the number is on your order confirmation and on our") }}
            <a href="{{ route('contact') }}" class="underline hover:text-brand-yellow-dark">{{ __('Contact us') }}</a>
            {{ __('page — or use the contact form there. The sooner you reach out, ideally within 24 hours of your order, the easier it is for us to look into it.') }}
        </p>

        <h2 class="text-lg font-bold mt-8 mb-3">{{ __('How refunds are processed') }}</h2>
        <p class="leading-relaxed mb-4">
            {{ __('For a cash order, a refund is given in cash or Mobile Money at the branch. For an order paid online by card or Mobile Money through Paystack, the refund is returned to the same payment method through Paystack — this can take a few business days to reflect, depending on your bank or Mobile Money provider, once the branch has approved it. A refund can also be partial where only part of an order was affected.') }}
        </p>

        <h2 class="text-lg font-bold mt-8 mb-3">{{ __("What isn't refunded") }}</h2>
        <p class="leading-relaxed mb-4">
            {{ __("An order that was correctly prepared and delivered or collected, with no issue reported, isn't eligible for a refund. Once a branch has accepted your order and started preparing it, a simple change of mind isn't grounds for a refund either.") }}
        </p>

        <h2 class="text-lg font-bold mt-8 mb-3">{{ __('Questions') }}</h2>
        <p class="leading-relaxed mb-4">
            {{ __('Get in touch through our') }}
            <a href="{{ route('contact') }}" class="underline hover:text-brand-yellow-dark">{{ __('Contact us') }}</a>
            {{ __('page and we\'ll help sort it out.') }}
        </p>
    </div>
</x-customer-layout>
