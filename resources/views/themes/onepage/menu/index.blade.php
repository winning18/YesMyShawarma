{{--
    One-page's /menu: the same menu as the home page's Menu section, kept
    as its own page for old links, SMS links and search results. Same data
    and structured data as the Classic menu (MenuController::index).
--}}
<x-customer-layout
    :title="$meta['title']"
    :og-description="$meta['description']"
    body-class="bg-brand-gray-50"
    main-class="max-w-6xl"
>
    <script type="application/ld+json">{!! json_encode($menuSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>

    <h1 class="font-heading text-5xl sm:text-6xl text-brand-black mb-6">{{ __('Menu') }}</h1>

    @include('themes.onepage._menu')

    @include('themes.onepage._cart-button')
</x-customer-layout>
