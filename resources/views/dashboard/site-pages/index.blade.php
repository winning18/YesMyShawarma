<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Website content') }}</h2>
    </x-slot>

    <div class="max-w-2xl mx-auto py-8 px-4 space-y-4">
        <p class="text-sm text-gray-500">{{ __('The words on the customer website: each page\'s text, and the title and description Google and WhatsApp show for it. About us, the FAQ and the policies only appear on the site once something is written on them.') }}</p>

        <div class="bg-white shadow rounded-lg flex items-center justify-between gap-4 px-5 py-4">
            <div>
                <p class="text-sm font-medium text-gray-800">{{ __('Website look') }}</p>
                <p class="text-xs text-gray-500">{{ __(\App\Services\Content\WebsiteThemeService::THEMES[$theme]['label']) }} · {{ __('Classic, Photo-led or One-page') }}</p>
            </div>
            <a href="{{ route('dashboard.website-look.edit') }}" class="text-sm font-semibold text-indigo-600 hover:text-indigo-800 shrink-0">{{ __('Change') }}</a>
        </div>

        <div class="bg-white shadow rounded-lg divide-y divide-gray-100">
            @foreach ($pages as $page)
                <div class="flex items-center justify-between gap-4 px-5 py-3">
                    <div>
                        <p class="text-sm font-medium text-gray-800">{{ __($page['label']) }}</p>
                        @if (! $page['published'])
                            <p class="text-xs text-gray-500">{{ __('Not written yet, so not on the site') }}</p>
                        @elseif (! $page['written'] && ! $page['optional'])
                            <p class="text-xs text-gray-500">{{ __('Using the standard wording') }}</p>
                        @endif
                    </div>
                    <a href="{{ route('dashboard.site-pages.edit', $page['slug']) }}" class="text-sm font-semibold text-indigo-600 hover:text-indigo-800 shrink-0">{{ __('Edit') }}</a>
                </div>
                @if ($page['slug'] === 'home')
                    <div class="flex items-center justify-between gap-4 px-5 py-3 bg-gray-50">
                        <p class="text-sm font-medium text-gray-800 pl-4">{{ __('Home page strips') }} <span class="text-xs font-normal text-gray-500">{{ __('(Classic look)') }}</span></p>
                        <a href="{{ route('dashboard.home-strips.edit') }}" class="text-sm font-semibold text-indigo-600 hover:text-indigo-800 shrink-0">{{ __('Edit') }}</a>
                    </div>
                @endif
                @if ($page['slug'] === 'faq')
                    <div class="flex items-center justify-between gap-4 px-5 py-3 bg-gray-50">
                        <p class="text-sm font-medium text-gray-800 pl-4">{{ __('FAQ questions') }}</p>
                        <a href="{{ route('dashboard.faq-items.index') }}" class="text-sm font-semibold text-indigo-600 hover:text-indigo-800 shrink-0">{{ __('Edit') }}</a>
                    </div>
                @endif
            @endforeach
        </div>
    </div>
</x-app-layout>
