<x-customer-layout title="Menu · {{ config('app.name') }}" body-class="menu-hero-bg" main-class="max-w-7xl">
    <div class="text-center mb-6">
        <h1 class="text-2xl font-bold">{{ __('Menu') }}: {{ $branch->name }}</h1>
        <a href="{{ route('branches.index') }}" class="text-sm underline text-brand-gray-500">{{ __('Change branch') }}</a>
    </div>

    @if ($errors->any())
        <div class="mb-6 rounded-lg bg-brand-red-light border border-brand-red text-brand-red-dark text-sm px-4 py-2">
            {{ $errors->first() }}
        </div>
    @endif

    {{--
        Side-by-side (compact, fixed-size thumbnail) for Drinks only,
        stacked (image on top, full card width) for everything else,
        including Hot Dogs/Loaded Fries — an explicit per-request choice,
        not a rule derived from anything about these categories. Side-by-
        side was previously abandoned everywhere because tying the
        square's size to the text column's height created a feedback loop
        for long, multi-line names — but a FIXED size (not measured/
        matched at all) never had that problem; it's what's used here.
    --}}
    @php $sideBySideCategories = ['drinks']; @endphp

    @php
        $searchIndex = $categories->flatMap(fn ($group) => $group['items']->map(fn ($item) => [
            'id' => $item->id,
            'name' => $item->name,
            'category' => $group['category']->name,
        ]))->values();
    @endphp

    <div x-data="menuSearch(@js($searchIndex))">
        <div class="mb-8 space-y-3">
            <div class="relative max-w-xl mx-auto">
                <input
                    type="search" x-model="query" @input="showSuggestions = true" @focus="showSuggestions = true"
                    @click.outside="showSuggestions = false"
                    placeholder="{{ __('Search our menu…') }}"
                    autocomplete="off"
                    class="w-full rounded-md border-2 border-brand-yellow bg-brand-black text-brand-white placeholder-brand-gray-400 focus:border-brand-yellow focus:ring-brand-yellow"
                >

                <div
                    x-show="showSuggestions && suggestions.length > 0" x-cloak
                    class="absolute z-20 mt-1 w-full bg-brand-white border border-brand-gray-200 rounded-md shadow-lg overflow-hidden"
                >
                    <template x-for="suggestion in suggestions" :key="suggestion.id">
                        <button
                            type="button" @click="pick(suggestion.name)"
                            class="block w-full text-left px-4 py-2 text-sm text-brand-black hover:bg-brand-yellow-light"
                        >
                            <span x-text="suggestion.name"></span>
                            <span class="text-brand-gray-500" x-text="' · ' + suggestion.category"></span>
                        </button>
                    </template>
                </div>
            </div>

            <div class="flex flex-wrap items-center justify-center gap-2">
                <button
                    type="button" @click="categoryFilter = 'all'"
                    :class="categoryFilter === 'all' ? 'bg-brand-yellow text-brand-black' : 'bg-brand-black text-brand-white border border-brand-yellow'"
                    class="whitespace-nowrap px-3 py-1 text-xs font-semibold rounded-full"
                >{{ __('All') }}</button>
                @foreach ($categories as $group)
                    <button
                        type="button" @click="categoryFilter = '{{ $group['category']->slug }}'"
                        :class="categoryFilter === '{{ $group['category']->slug }}' ? 'bg-brand-yellow text-brand-black' : 'bg-brand-black text-brand-white border border-brand-yellow'"
                        class="whitespace-nowrap px-3 py-1 text-xs font-semibold rounded-full"
                    >{{ $group['category']->name }}</button>
                @endforeach
            </div>

            <p
                x-show="query.trim() !== '' && !items.some((i) => i.name.toLowerCase().includes(query.trim().toLowerCase()))"
                x-cloak class="text-sm text-brand-gray-400 text-center"
            >{{ __('No menu items match your search.') }}</p>
        </div>

        @foreach ($categories as $group)
            <section
                class="mb-10"
                x-show="sectionVisible('{{ $group['category']->slug }}', @js($group['items']->pluck('name')))"
            >
                <h2 class="text-lg font-bold mb-4">{{ $group['category']->name }}</h2>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                    @foreach ($group['items'] as $item)
                        <div x-show="itemMatches(@js($item->name))">
                            @if (in_array($group['category']->slug, $sideBySideCategories, true))
                                <div class="bg-brand-black rounded-lg p-4 flex items-start gap-3">
                                    <a href="{{ route('menu.show', $item) }}" class="shrink-0">
                                        <x-product-image :item="$item" class="w-20 h-20" />
                                    </a>

                                    <div class="flex-1 min-w-0">
                                        @include('menu._item-details', ['item' => $item, 'branch' => $branch])
                                    </div>
                                </div>
                            @else
                                <div class="bg-brand-black rounded-lg p-4">
                                    <a href="{{ route('menu.show', $item) }}">
                                        <x-product-image :item="$item" class="w-full max-w-56 mx-auto aspect-square mb-4" />
                                    </a>

                                    @include('menu._item-details', ['item' => $item, 'branch' => $branch])
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </section>
        @endforeach
    </div>

    <script>
        function menuSearch(items) {
            return {
                items,
                query: '',
                categoryFilter: 'all',
                showSuggestions: false,

                get suggestions() {
                    const q = this.query.trim().toLowerCase();
                    if (q === '') return [];

                    return this.items.filter((i) => i.name.toLowerCase().includes(q)).slice(0, 8);
                },

                pick(name) {
                    this.query = name;
                    this.showSuggestions = false;
                },

                itemMatches(name) {
                    const q = this.query.trim().toLowerCase();

                    return q === '' || name.toLowerCase().includes(q);
                },

                sectionVisible(slug, itemNames) {
                    if (this.categoryFilter !== 'all' && this.categoryFilter !== slug) return false;

                    const q = this.query.trim().toLowerCase();
                    if (q === '') return true;

                    return itemNames.some((name) => name.toLowerCase().includes(q));
                },
            };
        }
    </script>

    @include('partials.menu-item-form-script')
</x-customer-layout>
