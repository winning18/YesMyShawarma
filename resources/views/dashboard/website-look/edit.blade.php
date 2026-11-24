<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Website content') }}: {{ __('Website look') }}</h2>
    </x-slot>

    <div class="max-w-3xl mx-auto py-8 px-4 space-y-4">
        <a href="{{ route('dashboard.site-pages.index') }}" class="text-sm text-gray-500 underline">&larr; {{ __('All pages') }}</a>

        @if (session('status'))
            <div class="rounded-md bg-green-50 text-green-700 text-sm px-4 py-2">{{ session('status') }}</div>
        @endif
        <x-error-summary />

        <p class="text-sm text-gray-500">{{ __('How the customer website looks. Every look uses your logo and brand colours (Business profile) and works with the same menu, cart and checkout. Preview a look first: only you see the preview, for an hour, until you save.') }}</p>

        <form method="POST" action="{{ route('dashboard.website-look.update') }}" class="space-y-3" x-data="{ theme: @js(old('theme', $chosen)) }">
            @csrf
            @method('PUT')

            @foreach ($themes as $option)
                <label class="block bg-white shadow rounded-lg p-5 cursor-pointer border-2 transition" :class="theme === @js($option['key']) ? 'border-indigo-500' : 'border-transparent'">
                    <div class="flex items-start gap-4">
                        <input type="radio" name="theme" value="{{ $option['key'] }}" x-model="theme" class="mt-1 text-indigo-600 focus:ring-indigo-500">
                        <div class="flex-1 min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <p class="text-base font-semibold text-gray-800">{{ __($option['label']) }}</p>
                                @if ($option['key'] === $chosen)
                                    <span class="text-xs font-semibold text-green-700 bg-green-50 px-2 py-0.5 rounded-full">{{ __('Live now') }}</span>
                                @endif
                            </div>
                            <p class="mt-1 text-sm text-gray-600">{{ __($option['summary']) }}</p>
                            <p class="mt-1 text-xs text-gray-500">{{ __('Fonts: :fonts', ['fonts' => $option['font']]) }}</p>

                            @if ($option['key'] === \App\Services\Content\WebsiteThemeService::PHOTO && $photos['total'] > 0 && $photos['with'] * 2 < $photos['total'])
                                <p class="mt-2 text-xs text-amber-700 bg-amber-50 rounded px-2 py-1">{{ __('Only :with of your :total dishes have a photo. Dishes without one show as a coloured tile with their name; add photos in Menu Editor for the best result.', $photos) }}</p>
                            @endif
                            @if ($option['key'] === \App\Services\Content\WebsiteThemeService::CLASSIC)
                                <p class="mt-2 text-xs text-gray-500">{{ __('Its home page rows are set under Home page strips.') }}</p>
                            @endif

                            <a href="{{ $option['previewUrl'] }}" target="_blank" rel="noopener" class="inline-block mt-3 text-sm font-semibold text-indigo-600 hover:text-indigo-800" @click.stop>{{ __('Preview') }} &rarr;</a>
                        </div>
                    </div>
                </label>
            @endforeach

            <div class="flex justify-end">
                <button type="submit" class="px-4 py-2 bg-indigo-600 text-white text-sm font-semibold rounded-md hover:bg-indigo-700">{{ __('Save look') }}</button>
            </div>
        </form>
    </div>
</x-app-layout>
