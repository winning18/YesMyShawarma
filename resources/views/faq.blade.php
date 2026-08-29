<x-customer-layout title="FAQ · {{ config('app.name') }}">
    <x-slot name="pageHeader">{{ __('Frequently asked questions') }}</x-slot>

    {{--
        Native <details>/<summary> rather than an Alpine accordion: no JS
        needed to render or expand it, which matters on the throttled-3G
        budget this site is built to (see CLAUDE.md's performance budget).
        Tailwind's group-open: variant (supported since 3.1, installed
        here) handles the plus-to-cross rotation.
    --}}
    <div class="max-w-3xl mx-auto divide-y divide-brand-gray-100">
        <details class="group py-4">
            <summary class="flex items-center justify-between gap-4 cursor-pointer list-none font-medium marker:content-none [&::-webkit-details-marker]:hidden">
                {{ __('Do I need an account to order?') }}
                <span class="shrink-0 text-brand-yellow-dark font-bold transition-transform group-open:rotate-45">+</span>
            </summary>
            <p class="mt-2 text-sm text-brand-gray-500">{{ __('No — check out as a guest with just your phone number. If you set a password later using that same number, your past orders are automatically linked to the new account.') }}</p>
        </details>
        <details class="group py-4">
            <summary class="flex items-center justify-between gap-4 cursor-pointer list-none font-medium marker:content-none [&::-webkit-details-marker]:hidden">
                {{ __('How can I pay?') }}
                <span class="shrink-0 text-brand-yellow-dark font-bold transition-transform group-open:rotate-45">+</span>
            </summary>
            <p class="mt-2 text-sm text-brand-gray-500">{{ __('Cash on pickup or delivery, or pay online upfront with Paystack — cards and Mobile Money (MoMo) are both supported.') }}</p>
        </details>
        <details class="group py-4">
            <summary class="flex items-center justify-between gap-4 cursor-pointer list-none font-medium marker:content-none [&::-webkit-details-marker]:hidden">
                {{ __('Do you deliver, or is it pickup only?') }}
                <span class="shrink-0 text-brand-yellow-dark font-bold transition-transform group-open:rotate-45">+</span>
            </summary>
            <p class="mt-2 text-sm text-brand-gray-500">{{ __('Both. Pickup is always available at any branch. Delivery is offered where we have an active delivery area, with the fee worked out by distance from the branch at checkout.') }}</p>
        </details>
        <details class="group py-4">
            <summary class="flex items-center justify-between gap-4 cursor-pointer list-none font-medium marker:content-none [&::-webkit-details-marker]:hidden">
                {{ __('How do I track my order?') }}
                <span class="shrink-0 text-brand-yellow-dark font-bold transition-transform group-open:rotate-45">+</span>
            </summary>
            <p class="mt-2 text-sm text-brand-gray-500">{{ __('Your order confirmation includes a tracking link. You can also look it up anytime from Track order using the phone number you ordered with.') }}</p>
        </details>
        <details class="group py-4">
            <summary class="flex items-center justify-between gap-4 cursor-pointer list-none font-medium marker:content-none [&::-webkit-details-marker]:hidden">
                {{ __('Which branches can I order from?') }}
                <span class="shrink-0 text-brand-yellow-dark font-bold transition-transform group-open:rotate-45">+</span>
            </summary>
            <p class="mt-2 text-sm text-brand-gray-500">{{ __('Ga Odumase and Pokuase Y-Junction are open now, with another branch on the way. Opening hours for each are shown on the Branches page.') }}</p>
        </details>
        <details class="group py-4">
            <summary class="flex items-center justify-between gap-4 cursor-pointer list-none font-medium marker:content-none [&::-webkit-details-marker]:hidden">
                {{ __("Something's wrong with my order — what do I do?") }}
                <span class="shrink-0 text-brand-yellow-dark font-bold transition-transform group-open:rotate-45">+</span>
            </summary>
            <p class="mt-2 text-sm text-brand-gray-500">{{ __('Call or WhatsApp the branch you ordered from, or send a message through Contact us — our team will sort out a refund or replacement.') }}</p>
        </details>
    </div>
</x-customer-layout>
