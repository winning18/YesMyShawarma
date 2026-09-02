<x-customer-layout :title="__('Something went wrong · :name', ['name' => config('app.name')])">
    <div class="max-w-xl mx-auto text-center">
        <div class="w-16 h-16 mx-auto rounded-full bg-brand-gray-100 flex items-center justify-center">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-8 h-8 text-brand-gray-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M12 9v4" />
                <path d="M12 17h.01" />
                <path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0Z" />
            </svg>
        </div>

        <h1 class="text-2xl font-bold mt-4">{{ __('Something went wrong on our end') }}</h1>
        <p class="text-brand-gray-500 mt-1">
            {{ __("That wasn't your fault — try again in a moment, or head back to the menu.") }}
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
