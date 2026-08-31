<x-customer-layout :title="__('Page not found · :name', ['name' => config('app.name')])">
    <div class="max-w-xl mx-auto text-center">
        <div class="w-16 h-16 mx-auto rounded-full bg-brand-gray-100 flex items-center justify-center">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-8 h-8 text-brand-gray-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="11" cy="11" r="7" />
                <path d="m21 21-4.35-4.35" />
                <path d="M9 11h4" />
            </svg>
        </div>

        <h1 class="text-2xl font-bold mt-4">{{ __("We couldn't find that page") }}</h1>
        <p class="text-brand-gray-500 mt-1">
            {{ __("The page you're looking for may have moved or no longer exists. Let's get you back to something tasty.") }}
        </p>
    </div>

    <div class="max-w-xl mx-auto mt-8 flex flex-col sm:flex-row gap-3">
        <a
            href="{{ route('menu.index') }}"
            class="flex-1 text-center px-6 py-3 bg-brand-yellow text-brand-black font-semibold rounded-md hover:bg-brand-yellow-dark"
        >
            {{ __('See our menu') }}
        </a>
        <a
            href="{{ route('home') }}"
            class="flex-1 text-center px-6 py-3 border border-brand-gray-300 font-semibold rounded-md hover:bg-brand-gray-100"
        >
            {{ __('Go to homepage') }}
        </a>
    </div>
</x-customer-layout>
