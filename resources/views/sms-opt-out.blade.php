<x-customer-layout :title="__('Stop promotional SMS · :name', ['name' => config('app.name')])">
    <x-slot name="pageHeader">{{ __('Promotional SMS') }}</x-slot>

    <div class="max-w-md mx-auto text-brand-black text-center space-y-4">
        @if ($done)
            <p class="text-lg font-bold">{{ __('You won\'t get any more promotional SMS from us.') }}</p>
            <p class="text-brand-gray-500">{{ __('You\'ll still get SMS updates about orders you place.') }}</p>
        @else
            <p class="text-lg font-bold">{{ __('Stop promotional SMS from :name?', ['name' => config('app.name')]) }}</p>
            <p class="text-brand-gray-500">{{ __('You\'ll still get SMS updates about orders you place.') }}</p>
            <form method="POST" action="{{ route('sms.opt-out.store', $code) }}">
                @csrf
                <button type="submit" class="px-6 py-2.5 bg-brand-yellow text-brand-black text-sm font-semibold rounded-md hover:bg-brand-yellow-dark">
                    {{ __('Yes, stop these messages') }}
                </button>
            </form>
        @endif

        <a href="{{ route('home') }}" class="inline-block text-sm underline text-brand-gray-500">{{ __('Back to the menu') }}</a>
    </div>
</x-customer-layout>
