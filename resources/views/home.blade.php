{{--
    Only page with no title before this — every other page follows
    "{page} · {app.name}", but the homepage IS the app name, so it gets
    its own descriptive, keyword-bearing title instead of falling back to
    the bare site name (CustomerLayout's default when $title is null).
--}}
<x-customer-layout
    :title="__(':name: Shawarma, Burgers & More in Accra', ['name' => config('app.name')])"
    body-class="bg-brand-black"
    :og-image="data_get($heroSlides->first(), 'imageUrl')"
>
    @foreach ($restaurantSchema as $branchSchema)
        <script type="application/ld+json">{!! json_encode($branchSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
    @endforeach

    <x-slot name="fullHero">
        {{--
            Full-bleed hero slider — each slide is a featured category with
            its own uploadable background image (staff dashboard → Hero
            images). Until a photo is uploaded for a category, it falls
            back to the same radial-glow black background used elsewhere
            on the site, so the hero never looks broken while photos are
            still being added.
        --}}
        <section
            x-data='{
                slides: @json($heroSlides),
                active: 0,
                timer: null,
                start() { this.timer = setInterval(() => this.next(), 5000) },
                stop() { clearInterval(this.timer) },
                next() { this.active = (this.active + 1) % this.slides.length },
                prev() { this.active = (this.active - 1 + this.slides.length) % this.slides.length },
            }'
            x-init="start()"
            @mouseenter="stop()"
            @mouseleave="start()"
            class="relative overflow-hidden"
            role="region"
            aria-label="{{ __('Featured menu categories') }}"
        >
            <h1 class="sr-only">{{ __('Order from :name', ['name' => config('app.name')]) }}</h1>

            <div class="relative h-[26rem] sm:h-[32rem]">
                <template x-for="(slide, index) in slides" :key="index">
                    <div
                        x-show="active === index"
                        x-transition:enter="transition ease-out duration-700" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
                        x-transition:leave="transition ease-in duration-300" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
                        class="absolute inset-0"
                    >
                        <div class="absolute inset-0 hero-slider-bg" x-show="!slide.imageUrl"></div>
                        <img :src="slide.imageUrl" :alt="slide.name" x-show="slide.imageUrl" class="absolute inset-0 w-full h-full object-cover">

                        {{--
                            On narrow screens object-cover fills the whole
                            width with image, so the desktop left-to-right
                            gradient (dark left, fading out by mid-image)
                            leaves the text sitting on a barely-darkened,
                            busy photo. A flat, stronger wash by default
                            fixes that; the directional stops take over at
                            sm: and up, where there's an actual text column
                            with room to breathe beside the image.

                            All three stops stay on the *same* gradient
                            utility throughout (bg-gradient-to-r never
                            changes) — an earlier version used a plain
                            bg-black/60 for the mobile default and only
                            overrode the gradient at sm:, but background-
                            color and background-image are different CSS
                            properties, so the flat color never actually
                            went away at sm: — it sat underneath the
                            gradient and made the whole image look dim on
                            desktop too. Keeping everything as gradient
                            stops means the sm: override actually replaces
                            the mobile one instead of stacking with it.
                        --}}
                        <div class="absolute inset-0 bg-gradient-to-r from-brand-black/60 via-brand-black/60 to-brand-black/60 sm:from-brand-black/85 sm:via-brand-black/50 sm:to-brand-black/10"></div>

                        <div class="relative h-full max-w-5xl mx-auto px-4 flex items-center">
                            <div class="max-w-md text-left">
                                <p class="text-brand-yellow uppercase tracking-wide text-sm font-semibold mb-2" x-text="slide.name"></p>
                                <p class="text-3xl sm:text-4xl font-bold text-brand-white mb-6" x-text="slide.tagline"></p>
                                <a href="{{ route('branches.index') }}" class="inline-block px-8 py-3 bg-brand-yellow text-brand-black font-bold rounded-md hover:bg-brand-yellow-dark">
                                    {{ __('ORDER NOW') }}
                                </a>
                            </div>
                        </div>
                    </div>
                </template>

                {{--
                    Arrows sit alongside the dots in one bottom control bar
                    rather than vertically centered on the side edges — the
                    left arrow previously overlapped the left-aligned
                    tagline text on narrow screens since both anchored to
                    the same left edge.
                --}}
                <div class="absolute bottom-5 left-1/2 -translate-x-1/2 flex items-center gap-4">
                    <button
                        @click="prev()" type="button" aria-label="{{ __('Previous slide') }}"
                        class="w-8 h-8 rounded-full bg-brand-black/40 text-brand-white flex items-center justify-center hover:bg-brand-black/60 transition"
                    >
                        <svg viewBox="0 0 24 24" fill="none" class="w-4 h-4"><path d="M15 18l-6-6 6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" /></svg>
                    </button>

                    <div class="flex gap-2">
                        <template x-for="(slide, index) in slides" :key="index">
                            <button
                                type="button"
                                @click="active = index"
                                :aria-label="'{{ __('Go to slide') }} ' + (index + 1)"
                                :class="active === index ? 'bg-brand-yellow' : 'bg-brand-white/40'"
                                class="w-2.5 h-2.5 rounded-full transition"
                            ></button>
                        </template>
                    </div>

                    <button
                        @click="next()" type="button" aria-label="{{ __('Next slide') }}"
                        class="w-8 h-8 rounded-full bg-brand-black/40 text-brand-white flex items-center justify-center hover:bg-brand-black/60 transition"
                    >
                        <svg viewBox="0 0 24 24" fill="none" class="w-4 h-4"><path d="M9 6l6 6-6 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" /></svg>
                    </button>
                </div>
            </div>
        </section>
    </x-slot>

    {{--
        The hero above gets a real uploaded photo per slide, so it doesn't
        need this — but everything below it sits on a dark body (see
        body-class on <x-customer-layout>) with a slow, blurred rotating
        gradient behind it (.home-blur-blob) instead of a flat black fill.
        Direction is picked once per page load (not persisted, so a reload
        can land on either), satisfying "clockwise and anti-clockwise after
        reload" without needing any server-side state for it.
    --}}
    <div class="relative overflow-hidden" x-data="{ dir: Math.random() < 0.5 ? 'rotate-cw' : 'rotate-ccw' }">
        <div class="absolute inset-0 -z-10 pointer-events-none" aria-hidden="true">
            <div class="home-blur-blob" :class="dir"></div>
        </div>

        {{--
            Continuous auto-scrolling strips (pure CSS animation, see
            .marquee-track / .marquee-track-left) rather than the hero's
            discrete slide-index slider — the two are different UX patterns
            on purpose. Direction alternates row to row. Each card links to
            that item's own product page, which already redirects to branch
            selection first if the visitor hasn't chosen one yet
            (MenuController@show) — clicking a specific item can't skip
            that step since price/availability are branch-scoped.

            A sold-out item ($item->isAvailable false — set in
            HomeController@itemsForSlugs from the selected branch's
            branch_menu_item pivot) stays visible but grayed out and
            unlinked instead: clicking through would otherwise 404, since
            MenuController@show 404s on an item that isn't available at
            the customer's selected branch.
        --}}
        @foreach ($menuSliders as $slider)
            <section class="mb-12">
                <h2 class="text-xl font-bold mb-4 text-brand-white">{{ __($slider['title']) }}</h2>
                <div class="overflow-hidden">
                    <div class="flex gap-4 w-max {{ $slider['direction'] === 'left' ? 'marquee-track-left' : 'marquee-track' }}">
                        @foreach ($slider['items']->concat($slider['items']) as $item)
                            @if ($item->isAvailable)
                                <a href="{{ route('menu.show', $item) }}" class="block w-40 shrink-0 group">
                                    <x-product-image :item="$item" class="w-40 h-40 mb-2 rounded-lg group-hover:opacity-90 transition" />
                                    <p class="text-sm font-semibold truncate text-brand-white">{{ $item->name }}</p>
                                    <p class="text-sm text-brand-gray-300">GH₵{{ number_format($item->base_price / 100, 2) }}</p>
                                </a>
                            @else
                                <div class="block w-40 shrink-0 relative" aria-disabled="true">
                                    <div class="relative">
                                        <x-product-image :item="$item" class="w-40 h-40 mb-2 rounded-lg opacity-40 grayscale" />
                                        <span class="absolute top-2 left-2 bg-brand-black/80 text-brand-white text-[11px] font-semibold uppercase tracking-wide px-2 py-0.5 rounded">
                                            {{ __('Sold out') }}
                                        </span>
                                    </div>
                                    <p class="text-sm font-semibold truncate text-brand-gray-400">{{ $item->name }}</p>
                                    <p class="text-sm text-brand-gray-500">GH₵{{ number_format($item->base_price / 100, 2) }}</p>
                                </div>
                            @endif
                        @endforeach
                    </div>
                </div>
            </section>
        @endforeach
    </div>
</x-customer-layout>
