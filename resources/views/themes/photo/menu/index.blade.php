{{--
    Photo-led menu page: a sticky bar of categories, then each category as
    a grid of large photo cards with a quick Add. Same data and structured
    data as the Classic menu (MenuController::index).
--}}
<x-customer-layout
    :title="$meta['title']"
    :og-description="$meta['description']"
    body-class="bg-brand-white"
    main-class="max-w-6xl"
>
    <script type="application/ld+json">{!! json_encode($menuSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>

    <div class="text-center mb-6">
        <h1 class="font-heading text-4xl sm:text-5xl text-brand-black mb-4">{{ __('Menu') }}</h1>
        <x-customer-branch-bar :branch="$branch" class="max-w-xl mx-auto" />
    </div>

    <x-error-summary class="mb-6" />

    @include('themes.partials.category-bar', ['categories' => $categories])

    @foreach ($categories as $group)
        <section id="cat-{{ $group['category']->slug }}" class="scroll-mt-36 py-6">
            <h2 class="font-heading text-3xl text-brand-black mb-6">{{ $group['category']->name }}</h2>
            <div class="grid grid-cols-2 lg:grid-cols-3 gap-x-4 sm:gap-x-6 gap-y-8">
                @foreach ($group['items'] as $item)
                    @include('themes.partials.dish-card', [
                        'item' => $item,
                        'branch' => $branch,
                        'anchor' => 'cat-'.$group['category']->slug,
                        'isPopular' => in_array($item->id, $popularIds, true),
                    ])
                @endforeach
            </div>
        </section>
    @endforeach
</x-customer-layout>
