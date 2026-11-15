<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Website content') }}: {{ __($config['label']) }}</h2>
    </x-slot>

    @php
        $placeholders = [':name' => __('business name'), ':city' => __('city, from the Business profile')] + collect($config['placeholders'])->mapWithKeys(fn ($p) => [$p => match ($p) {
            ':branches' => __('the branch names'),
            ':branch' => __('this branch\'s name'),
            ':area' => __('the branch name without "Branch"'),
            ':address' => __('the branch address'),
            ':phone' => __('the branch phone'),
            ':item' => __('the item\'s name'),
            ':url' => __('this website\'s address'),
            default => $p,
        }])->all();
    @endphp

    <div class="max-w-3xl mx-auto py-8 px-4 space-y-4">
        <a href="{{ route('dashboard.site-pages.index') }}" class="text-sm text-gray-500 underline">&larr; {{ __('All pages') }}</a>

        @if (session('status'))
            <div class="rounded-md bg-green-50 text-green-700 text-sm px-4 py-2">{{ session('status') }}</div>
        @endif

        <form method="POST" action="{{ route('dashboard.site-pages.update', $slug) }}" class="bg-white shadow rounded-lg p-6 space-y-6">
            @csrf
            @method('PUT')

            <div class="rounded-md bg-gray-50 px-4 py-3 text-xs text-gray-600">
                <p class="font-semibold mb-1">{{ __('You can use these in the text, and they are filled in on the page:') }}</p>
                <ul class="space-y-0.5">
                    @foreach ($placeholders as $placeholder => $meaning)
                        <li><code class="text-gray-800">{{ $placeholder }}</code> {{ $meaning }}</li>
                    @endforeach
                </ul>
            </div>

            @if ($config['heading'])
                <div>
                    <x-input-label for="title" :value="$slug === 'branch' ? __('Introduction heading') : __('Page heading')" />
                    <x-text-input id="title" name="title" class="mt-1 block w-full" :value="old('title', $page->title)" maxlength="255" />
                    <x-input-error class="mt-2" :messages="$errors->get('title')" />
                </div>
            @endif

            @if ($about)
                <div>
                    <x-input-label for="mission" :value="__('Mission (optional)')" />
                    <textarea id="mission" name="mission" rows="2" maxlength="1000" class="mt-1 block w-full rounded-md border-gray-300 text-sm">{{ old('mission', $about['mission']) }}</textarea>
                    <x-input-error class="mt-2" :messages="$errors->get('mission')" />
                </div>
                <div>
                    <x-input-label for="vision" :value="__('Vision (optional)')" />
                    <textarea id="vision" name="vision" rows="2" maxlength="1000" class="mt-1 block w-full rounded-md border-gray-300 text-sm">{{ old('vision', $about['vision']) }}</textarea>
                    <x-input-error class="mt-2" :messages="$errors->get('vision')" />
                </div>
            @endif

            @if ($config['body'])
                <div>
                    <x-input-label for="body" :value="match ($slug) { 'about' => __('Your story (optional)'), 'branch' => __('Introduction (optional)'), 'llms' => __('Summary (leave empty to build one from your pages)'), default => __('Page text') }" />
                    <textarea id="body" name="body" rows="{{ in_array($slug, ['branch'], true) ? 4 : 18 }}" class="mt-1 block w-full rounded-md border-gray-300 text-sm font-mono">{{ old('body', $page->body) }}</textarea>
                    @if ($slug !== 'llms')
                        <p class="text-xs text-gray-500 mt-1">{{ __('Leave a blank line between paragraphs. ## at the start of a line makes a heading, - a bullet point, **two stars** bold, and [words](https://link) a link.') }}</p>
                    @endif
                    @if (in_array($slug, \App\Services\Content\SitePageService::OPTIONAL_PAGES, true) && $slug !== 'about')
                        <p class="text-xs text-gray-500 mt-1">{{ __('Empty, the page is taken off the site.') }}</p>
                    @endif
                    <x-input-error class="mt-2" :messages="$errors->get('body')" />
                </div>
            @endif

            @if ($about)
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <x-input-label for="founded_year" :value="__('Year you started (optional)')" />
                        <x-text-input id="founded_year" name="founded_year" type="number" min="1900" max="{{ now()->year }}" class="mt-1 block w-full" :value="old('founded_year', $about['founded_year'])" />
                        <p class="text-xs text-gray-500 mt-1">{{ __('Shows "years of operation".') }}</p>
                        <x-input-error class="mt-2" :messages="$errors->get('founded_year')" />
                    </div>
                    <div>
                        <x-input-label for="customers_label" :value="__('Happy customers (optional)')" />
                        <x-text-input id="customers_label" name="customers_label" class="mt-1 block w-full" :value="old('customers_label', $about['customers_label'])" maxlength="40" placeholder="{{ __('e.g. 2,000+') }}" />
                        <x-input-error class="mt-2" :messages="$errors->get('customers_label')" />
                    </div>
                </div>
            @endif

            @if ($config['meta'])
                <section class="space-y-4 border-t border-gray-100 pt-6">
                    <h3 class="font-semibold text-gray-800">{{ __('In Google and when shared') }}</h3>
                    @if ($slug !== 'default')
                        <div>
                            <x-input-label for="meta_title" :value="__('Title (leave empty for the standard one)')" />
                            <x-text-input id="meta_title" name="meta_title" class="mt-1 block w-full" :value="old('meta_title', $page->meta_title)" maxlength="255" />
                            <p class="text-xs text-gray-500 mt-1">{{ __('Best under 60 characters.') }}</p>
                            <x-input-error class="mt-2" :messages="$errors->get('meta_title')" />
                        </div>
                    @endif
                    @if ($slug !== 'menu-item')
                        <div>
                            <x-input-label for="meta_description" :value="$slug === 'default' ? __('Description for pages without their own') : __('Description (leave empty to use the general one)')" />
                            <textarea id="meta_description" name="meta_description" rows="2" maxlength="500" class="mt-1 block w-full rounded-md border-gray-300 text-sm">{{ old('meta_description', $page->meta_description) }}</textarea>
                            <p class="text-xs text-gray-500 mt-1">{{ __('Best under 160 characters.') }}</p>
                            <x-input-error class="mt-2" :messages="$errors->get('meta_description')" />
                        </div>
                    @else
                        <p class="text-xs text-gray-500">{{ __('Each item\'s description comes from the item itself in the Menu Editor.') }}</p>
                    @endif
                </section>
            @endif

            <div class="flex items-center gap-4">
                <x-primary-button>{{ __('Save') }}</x-primary-button>
                @if ($publicUrl)
                    <a href="{{ $publicUrl }}" target="_blank" rel="noopener" class="text-sm text-gray-500 underline">{{ __('View on the website') }}</a>
                @endif
            </div>
        </form>
    </div>
</x-app-layout>
