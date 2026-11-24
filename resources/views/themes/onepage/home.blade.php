{{--
    One-page home (WebsiteThemeService::ONE_PAGE): the whole site in one
    scroll, in numbered sections the header links to: Menu, About (when
    written), Branches and Contact. Data from HomeController and
    ThemeHomeService; the menu is the same as /menu (themes/onepage/_menu).
--}}
@php
    $sections = array_values(array_filter([
        'menu' => __('Menu'),
        'about' => $about ? __('About') : null,
        'branches' => __('Branches'),
        'contact' => __('Contact'),
    ]));
    $number = fn (string $label) => sprintf('%02d', array_search($label, $sections, true) + 1);
@endphp
<x-customer-layout
    :title="$meta['title']"
    body-class="bg-brand-gray-50"
    main-class="max-w-6xl"
    :og-image="$hero['imageUrl']"
    :og-description="$meta['description']"
>
    @foreach ($restaurantSchema as $branchSchema)
        <script type="application/ld+json">{!! json_encode($branchSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>
    @endforeach

    <x-slot name="fullHero">
        @include('themes.partials.promotion-banner')

        <section class="bg-brand-yellow-light overflow-hidden">
            <div class="max-w-6xl mx-auto px-4 py-12 sm:py-16 grid grid-cols-1 md:grid-cols-2 gap-10 items-center">
                <div>
                    <p class="inline-block mb-5 px-3 py-1 rounded-full bg-brand-surface text-brand-on-surface text-xs font-semibold uppercase tracking-wider">{{ $hero['eyebrow'] }}</p>
                    <h1 class="font-heading text-5xl sm:text-6xl lg:text-7xl leading-[0.95] text-brand-black">{{ $hero['headline'] }}</h1>
                    <div class="mt-8 flex flex-wrap gap-3">
                        <a href="#menu" class="inline-flex items-center px-7 py-3 rounded-full bg-brand-surface text-brand-on-surface font-semibold hover:opacity-90 transition">{{ __('See the menu') }}</a>
                        <a href="#branches" class="inline-flex items-center px-7 py-3 rounded-full border-2 border-brand-black text-brand-black font-semibold hover:bg-brand-surface hover:text-brand-on-surface transition">{{ __('Find a branch') }}</a>
                    </div>
                </div>

                <div class="relative">
                    <div class="absolute -inset-3 sm:-inset-4 translate-x-4 translate-y-4 rounded-[2rem] bg-brand-red" aria-hidden="true"></div>
                    <div class="relative aspect-[4/3] md:aspect-[4/5] overflow-hidden rounded-[2rem] bg-brand-yellow">
                        @if ($hero['imageUrl'])
                            <img src="{{ $hero['imageUrl'] }}" alt="" fetchpriority="high" decoding="async" class="absolute inset-0 w-full h-full object-cover">
                        @else
                            <span class="absolute inset-0 flex items-center justify-center p-8 text-center font-heading text-4xl text-brand-on-primary">{{ $hero['eyebrow'] }}</span>
                        @endif
                    </div>
                </div>
            </div>
        </section>

        @include('themes.partials.quick-facts')
    </x-slot>

    {{-- 01 Menu --}}
    <section id="menu" class="scroll-mt-20 py-6">
        <p class="text-sm font-semibold text-brand-accent-text mb-1">{{ $number(__('Menu')) }} —</p>
        <h2 class="font-heading text-4xl sm:text-5xl text-brand-black mb-6">{{ __('Menu') }}</h2>
        @include('themes.onepage._menu')
    </section>

    @if ($reviews->isNotEmpty())
        <section class="py-10">
            <h2 class="font-heading text-3xl text-brand-black mb-6">{{ __('What customers say') }}</h2>
            @include('themes.partials.reviews')
        </section>
    @endif

    {{-- About (only when Website content → About us is written) --}}
    @if ($about)
        <section id="about" class="scroll-mt-20 py-10 border-t-2 border-brand-black mt-6">
            <p class="text-sm font-semibold text-brand-accent-text mb-1">{{ $number(__('About')) }} —</p>
            <h2 class="font-heading text-4xl sm:text-5xl text-brand-black mb-6">{{ $about['heading'] ?: __('About us') }}</h2>
            <div class="grid grid-cols-1 md:grid-cols-5 gap-8">
                @if ($about['mission'])
                    <p class="md:col-span-2 font-heading text-2xl leading-snug text-brand-black">{{ $about['mission'] }}</p>
                @endif
                @if ($about['story'])
                    <div class="{{ $about['mission'] ? 'md:col-span-3' : 'md:col-span-5' }}">
                        <div class="text-brand-gray-700 leading-relaxed page-content-plain line-clamp-[10]">{{ $about['story'] }}</div>
                        <a href="{{ route('about') }}" class="inline-block mt-4 text-sm font-semibold underline underline-offset-4">{{ __('Read our story') }}</a>
                    </div>
                @endif
            </div>
        </section>
    @endif

    {{-- Branches --}}
    <section id="branches" class="scroll-mt-20 py-10 border-t-2 border-brand-black mt-6">
        <p class="text-sm font-semibold text-brand-accent-text mb-1">{{ $number(__('Branches')) }} —</p>
        <h2 class="font-heading text-4xl sm:text-5xl text-brand-black mb-6">{{ trans_choice('Our branch|Our branches', $branches->count()) }}</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
            @foreach ($branches as $branchCard)
                <x-branch-card :branch="$branchCard" :selected="$branchCard->id === $selectedBranchId" class="bg-brand-white" />
            @endforeach
        </div>
    </section>

    {{-- Contact --}}
    <section id="contact" class="scroll-mt-20 py-10 border-t-2 border-brand-black mt-6 mb-16">
        <p class="text-sm font-semibold text-brand-accent-text mb-1">{{ $number(__('Contact')) }} —</p>
        <h2 class="font-heading text-4xl sm:text-5xl text-brand-black mb-6">{{ __('Get in touch') }}</h2>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            @if ($contact['phones'] !== [])
                <div class="rounded-2xl bg-brand-white border border-brand-gray-100 p-6">
                    <p class="text-xs uppercase tracking-wider text-brand-gray-500 mb-3">{{ __('Call us') }}</p>
                    @foreach ($contact['phones'] as $phone)
                        <a href="tel:{{ $phone['tel'] }}" class="block mb-3 hover:text-brand-accent-text">
                            <span class="block font-semibold text-brand-black">{{ $phone['name'] }}</span>
                            <span class="font-heading text-xl">{{ $phone['display'] }}</span>
                        </a>
                    @endforeach
                </div>
            @endif
            @if ($contact['emails'] !== [])
                <div class="rounded-2xl bg-brand-white border border-brand-gray-100 p-6">
                    <p class="text-xs uppercase tracking-wider text-brand-gray-500 mb-3">{{ __('Email us') }}</p>
                    @foreach ($contact['emails'] as $email)
                        <a href="mailto:{{ $email }}" class="block mb-2 font-semibold text-brand-black break-all hover:text-brand-accent-text">{{ $email }}</a>
                    @endforeach
                </div>
            @endif
            <div class="rounded-2xl bg-brand-surface text-brand-on-surface p-6 flex flex-col">
                <p class="text-xs uppercase tracking-wider text-brand-on-surface/70 mb-3">{{ __('Send a message') }}</p>
                <p class="text-sm text-brand-on-surface/70 flex-1">{{ __('Questions, catering or feedback: write to us and we\'ll reply.') }}</p>
                <a href="{{ route('contact') }}" class="mt-4 inline-flex self-start items-center px-5 py-2.5 rounded-full bg-brand-white text-brand-black text-sm font-semibold hover:bg-brand-gray-100 transition">{{ __('Contact form') }}</a>
            </div>
        </div>
    </section>

    @include('themes.onepage._cart-button')
</x-customer-layout>
