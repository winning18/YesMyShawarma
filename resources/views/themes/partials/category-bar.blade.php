{{--
    A sticky, swipeable row of the menu's categories, each jumping to its
    section (#cat-<slug>). A ?category=<slug> link from elsewhere (the home
    page's tiles, the Classic "Show all" links) opens at that category.
--}}
@if ($categories->count() > 1)
    <nav
        x-data="{ active: null }"
        x-init="
            const wanted = new URLSearchParams(location.search).get('category');
            if (wanted && ! location.hash) document.getElementById('cat-' + wanted)?.scrollIntoView();
        "
        class="sticky top-16 z-30 -mx-4 px-4 py-3 bg-brand-white/95 backdrop-blur border-b border-brand-gray-100"
        aria-label="{{ __('Menu categories') }}"
    >
        <div class="overflow-x-auto scrollbar-hide">
            <div class="flex gap-2 w-max">
                @foreach ($categories as $group)
                    <a
                        href="#cat-{{ $group['category']->slug }}"
                        @click="active = @js($group['category']->slug)"
                        :class="active === @js($group['category']->slug) ? 'bg-brand-black text-brand-white border-brand-black' : 'bg-brand-white text-brand-black border-brand-gray-300'"
                        class="whitespace-nowrap px-4 py-1.5 text-sm font-semibold rounded-full border transition"
                    >{{ $group['category']->name }}</a>
                @endforeach
            </div>
        </div>
    </nav>
@endif
