{{--
    Photo-led home page (WebsiteThemeService::PHOTO): one big photo with a
    short headline, the quick facts, then the food: favourites, the menu's
    categories as photo tiles, a gallery and reviews. Data from
    HomeController and ThemeHomeService.
--}}
<x-customer-layout
    :title="$meta['title']"
    body-class="bg-brand-white"
    main-class="max-w-6xl"
    :og-image="$hero['imageUrl']"
    :og-description="$meta['description']"
>
    @foreach ($restaurantSchema as $branchSchema)
        <script type="application/ld+json">{!! json_encode($branchSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>
    @endforeach

    <x-slot name="fullHero">
        @include('themes.partials.promotion-banner')

        <section class="relative h-[72vh] min-h-[26rem] max-h-[44rem] overflow-hidden {{ $hero['imageUrl'] ? 'bg-brand-black' : 'bg-brand-yellow' }}">
            @if ($hero['imageUrl'])
                <img src="{{ $hero['imageUrl'] }}" alt="" fetchpriority="high" decoding="async" class="absolute inset-0 w-full h-full object-cover">
                <div class="absolute inset-0 bg-gradient-to-t from-brand-black/80 via-brand-black/30 to-brand-black/10"></div>
            @endif

            <div class="relative h-full max-w-6xl mx-auto px-4 pb-12 sm:pb-16 flex flex-col justify-end">
                <p class="uppercase tracking-[0.2em] text-xs sm:text-sm font-semibold mb-3 {{ $hero['imageUrl'] ? 'text-brand-white/80' : 'text-brand-on-primary/80' }}">{{ $hero['eyebrow'] }}</p>
                <h1 class="font-heading text-4xl sm:text-6xl leading-[1.05] max-w-3xl {{ $hero['imageUrl'] ? 'text-brand-white' : 'text-brand-on-primary' }}">{{ $hero['headline'] }}</h1>
                <div class="mt-8 flex flex-wrap gap-3">
                    <a href="{{ route('menu.index') }}" class="inline-flex items-center px-7 py-3 rounded-full font-semibold transition {{ $hero['imageUrl'] ? 'bg-brand-yellow text-brand-on-primary hover:bg-brand-yellow-dark' : 'bg-brand-surface text-brand-on-surface hover:opacity-90' }}">{{ __('Order now') }}</a>
                    <a href="{{ route('branches.index') }}" class="inline-flex items-center px-7 py-3 rounded-full border font-semibold transition {{ $hero['imageUrl'] ? 'border-brand-white/70 text-brand-white hover:bg-brand-white hover:text-brand-black' : 'border-brand-black text-brand-black hover:bg-brand-surface hover:text-brand-on-surface' }}">{{ __('Find a branch') }}</a>
                </div>
            </div>
        </section>

        @include('themes.partials.quick-facts')
    </x-slot>

    @if ($featured->isNotEmpty())
        <section class="py-8">
            <div class="flex items-end justify-between gap-4 mb-6">
                <h2 class="font-heading text-3xl text-brand-black">{{ $popularItems->isNotEmpty() ? __('Most ordered this month') : __('Our favourites') }}</h2>
                <a href="{{ route('menu.index') }}" class="text-sm font-semibold underline underline-offset-4 shrink-0">{{ __('Full menu') }}</a>
            </div>
            <div class="grid grid-cols-2 lg:grid-cols-3 gap-x-4 sm:gap-x-6 gap-y-8">
                @foreach ($featured as $item)
                    @include('themes.partials.dish-card', ['item' => $item, 'branch' => null, 'isPopular' => false])
                @endforeach
            </div>
        </section>
    @endif

    @if ($categoryTiles->isNotEmpty())
        <section class="py-8">
            <h2 class="font-heading text-3xl text-brand-black mb-6">{{ __('Explore the menu') }}</h2>
            <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
                @foreach ($categoryTiles as $tile)
                    <a href="{{ route('menu.index', ['category' => $tile['slug']]) }}" class="group relative block aspect-[4/3] overflow-hidden rounded-2xl {{ $tile['imageUrl'] ? 'bg-brand-black' : 'bg-brand-yellow-light' }}">
                        @if ($tile['imageUrl'])
                            <img src="{{ $tile['imageUrl'] }}" alt="" loading="lazy" decoding="async" class="absolute inset-0 w-full h-full object-cover opacity-90 group-hover:scale-[1.04] transition duration-500">
                            <span class="absolute inset-0 bg-gradient-to-t from-brand-black/70 to-transparent"></span>
                        @endif
                        <span class="absolute bottom-0 left-0 p-4 font-heading text-xl sm:text-2xl {{ $tile['imageUrl'] ? 'text-brand-white' : 'text-brand-black' }}">{{ $tile['name'] }}</span>
                    </a>
                @endforeach
            </div>
        </section>
    @endif

    @if ($gallery->count() >= 3)
        <section class="py-8">
            <h2 class="font-heading text-3xl text-brand-black mb-6">{{ __('From our kitchen') }}</h2>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                @foreach ($gallery->take(5) as $item)
                    <a href="{{ route('menu.show', $item) }}" class="relative block overflow-hidden rounded-xl bg-brand-gray-100 {{ $loop->first ? 'col-span-2 row-span-2 aspect-square' : 'aspect-square' }}">
                        <x-product-image :item="$item" class="absolute inset-0 w-full h-full rounded-none hover:scale-[1.04] transition duration-500" />
                    </a>
                @endforeach
            </div>
        </section>
    @endif

    @if ($reviews->isNotEmpty())
        <section class="py-8">
            <h2 class="font-heading text-3xl text-brand-black mb-6">{{ __('What customers say') }}</h2>
            @include('themes.partials.reviews')
        </section>
    @endif

    <section class="my-10 rounded-3xl bg-brand-yellow text-brand-on-primary px-6 py-12 sm:px-12 text-center">
        <h2 class="font-heading text-3xl sm:text-4xl">{{ __('Hungry? Your order is a few taps away.') }}</h2>
        <a href="{{ route('menu.index') }}" class="inline-flex mt-6 items-center px-8 py-3 rounded-full bg-brand-surface text-brand-on-surface font-semibold hover:opacity-90 transition">{{ __('Order now') }}</a>
    </section>
</x-customer-layout>
