<x-customer-layout title="About us · {{ config('app.name') }}">
    <h1 class="text-2xl font-bold mb-6">{{ __('About us') }}</h1>

    <div class="text-brand-black space-y-4">
        <p>
            {{ __(':name serves shawarma, burgers, sandwiches, hot dogs, loaded fries and drinks from two branches in Accra — Ga Odumase and Pokuase Y-Junction.', ['name' => config('app.name')]) }}
        </p>
        <p>
            {{ __('Order online for pickup, or find us on Instagram, Facebook and TikTok.') }}
        </p>
    </div>
</x-customer-layout>
