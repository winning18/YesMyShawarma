<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Website content') }}: {{ __('Home page strips') }}</h2>
    </x-slot>

    @php
        $initial = old('strips', $strips);
        $initial = array_values(array_map(fn ($strip) => ['title' => $strip['title'] ?? '', 'slugs' => array_values($strip['slugs'] ?? [])], $initial));
    @endphp

    <div class="max-w-3xl mx-auto py-8 px-4 space-y-4">
        <a href="{{ route('dashboard.site-pages.index') }}" class="text-sm text-gray-500 underline">&larr; {{ __('All pages') }}</a>

        @if (session('status'))
            <div class="rounded-md bg-green-50 text-green-700 text-sm px-4 py-2">{{ session('status') }}</div>
        @endif
        <x-error-summary />

        <p class="text-sm text-gray-500">
            {{ __('The rows of menu items on the home page, top to bottom. Each shows the items from the categories you tick.') }}
            @unless ($isCustom)
                {{ __('Right now there\'s one strip per category; save to use your own.') }}
            @endunless
        </p>

        <form method="POST" action="{{ route('dashboard.home-strips.update') }}" class="space-y-3"
            x-data="{ strips: @js($initial), move(i, by) { const s = this.strips.splice(i, 1)[0]; this.strips.splice(i + by, 0, s); } }">
            @csrf
            @method('PUT')

            <template x-for="(strip, i) in strips" :key="i">
                <div class="bg-white shadow rounded-lg p-5 space-y-3">
                    <div class="flex items-end gap-3">
                        <div class="flex-1">
                            <label class="block text-sm font-medium text-gray-700" :for="'strip-title-' + i">{{ __('Title') }}</label>
                            <input type="text" :id="'strip-title-' + i" :name="'strips[' + i + '][title]'" x-model="strip.title" maxlength="60" required class="mt-1 block w-full rounded-md border-gray-300 text-sm">
                        </div>
                        <div class="flex gap-1 shrink-0">
                            <button type="button" @click="move(i, -1)" :disabled="i === 0" class="px-2 py-2 text-sm border border-gray-300 rounded-md disabled:opacity-30" aria-label="{{ __('Move up') }}">&uarr;</button>
                            <button type="button" @click="move(i, 1)" :disabled="i === strips.length - 1" class="px-2 py-2 text-sm border border-gray-300 rounded-md disabled:opacity-30" aria-label="{{ __('Move down') }}">&darr;</button>
                            <button type="button" @click="strips.splice(i, 1)" x-show="strips.length > 1" class="px-3 py-2 text-sm text-red-600 border border-gray-300 rounded-md">{{ __('Remove') }}</button>
                        </div>
                    </div>
                    <fieldset>
                        <legend class="text-sm font-medium text-gray-700">{{ __('Categories') }}</legend>
                        <div class="mt-1 flex flex-wrap gap-x-5 gap-y-1">
                            @foreach ($categories as $category)
                                <label class="flex items-center gap-2 text-sm">
                                    <input type="checkbox" :name="'strips[' + i + '][slugs][]'" value="{{ $category->slug }}" x-model="strip.slugs" class="rounded border-gray-300">
                                    {{ $category->name }}
                                </label>
                            @endforeach
                        </div>
                    </fieldset>
                </div>
            </template>

            <button type="button" @click="strips.push({ title: '', slugs: [] })" class="text-sm font-semibold text-indigo-600 hover:text-indigo-800">+ {{ __('Add a strip') }}</button>

            <div class="flex flex-wrap items-center gap-4 pt-2">
                <x-primary-button>{{ __('Save strips') }}</x-primary-button>
                @if ($isCustom)
                    <button type="submit" name="reset" value="1" formnovalidate onclick="return confirm(@js(__('Go back to one strip per category?')))" class="text-sm text-gray-500 underline">{{ __('Use one strip per category instead') }}</button>
                @endif
                <a href="{{ route('home') }}" target="_blank" rel="noopener" class="text-sm text-gray-500 underline">{{ __('View the home page') }}</a>
            </div>
        </form>
    </div>
</x-app-layout>
