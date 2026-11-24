{{--
    An item's own page in the Photo-led and One-page themes: a large photo
    beside the dish, the same add-to-cart form and drinks as Classic
    (menu/_item-form, menu/_drinks). Data from MenuController::show.
--}}
<x-customer-layout
    :title="$metaTitle"
    :og-image="$item->imageUrl()"
    :og-description="$metaDescription"
    body-class="bg-brand-white"
    main-class="max-w-6xl"
>
    <script type="application/ld+json">{!! json_encode($productSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>

    <a href="{{ $theme === \App\Services\Content\WebsiteThemeService::ONE_PAGE ? route('home').'#menu' : route('menu.index') }}" class="inline-flex items-center gap-1 text-sm font-medium text-brand-gray-500 hover:text-brand-black">&larr; {{ __('Back to menu') }}</a>

    @if ($errors->any())
        <div class="mt-6 rounded-lg bg-brand-red-light border border-brand-red text-brand-red-dark text-sm px-4 py-2">
            {{ $errors->first() }}
        </div>
    @endif

    {{-- Without a photo the dish takes the whole width: no empty picture box. --}}
    <div class="grid grid-cols-1 {{ $item->imageUrl() ? 'md:grid-cols-2' : 'max-w-2xl' }} gap-8 md:gap-12 mt-6 mb-14">
        @if ($item->imageUrl())
            <div class="relative aspect-square overflow-hidden rounded-3xl bg-brand-yellow-light">
                <x-product-image :item="$item" class="absolute inset-0 w-full h-full rounded-none" />
            </div>
        @endif

        <div class="md:py-4">
            @if ($popularRank)
                <p class="inline-block mb-4 bg-brand-yellow text-brand-on-primary text-xs font-bold uppercase tracking-wide px-3 py-1 rounded-full">
                    {{ $popularRank === 1 ? __('Most ordered this month') : __('#:rank most ordered this month', ['rank' => $popularRank]) }}
                </p>
            @endif

            <h1 class="font-heading text-4xl sm:text-5xl leading-tight text-brand-black mb-3">{{ $item->name }}</h1>

            @if ($item->description)
                <p class="text-brand-gray-500 text-lg leading-relaxed mb-5">{{ $item->description }}</p>
            @endif

            <p class="font-heading text-3xl text-brand-black mb-6">GH₵{{ number_format($item->base_price / 100, 2) }}</p>

            <x-customer-branch-bar :branch="$branch" class="mb-6" />

            @include('menu._item-form')
        </div>
    </div>

    @include('menu._drinks')

    @include('partials.menu-item-form-script')
</x-customer-layout>
