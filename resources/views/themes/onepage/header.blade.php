{{--
    One-page header: numbered links that scroll to the home page's sections
    (from any other page they open the home page at that section).
--}}
<header class="bg-brand-white sticky top-0 z-40 border-b-2 border-brand-black">
    <div class="max-w-6xl mx-auto px-4 h-16 md:h-20 flex items-center justify-between gap-6">
        <a href="{{ route('home') }}" class="shrink-0">
            <x-brand-logo class="h-11 md:h-14 w-auto max-w-[11rem] md:max-w-[15rem] object-contain" text-class="font-heading text-xl text-brand-black" />
        </a>

        @if ($websiteOn)
            <nav class="hidden md:flex items-center gap-6 text-sm font-semibold text-brand-black">
                @foreach ($customerNav as $link)
                    <a href="{{ $link['url'] }}" class="group inline-flex items-baseline gap-1 hover:text-brand-accent-text transition">
                        @if ($link['number'])
                            <span class="text-[11px] text-brand-gray-500 group-hover:text-brand-accent-text">{{ $link['number'] }}</span>
                        @endif
                        {{ $link['label'] }}
                    </a>
                @endforeach
            </nav>

            @include('themes.partials.header-actions', ['orderUrl' => route('home').'#menu'])
        @endif
    </div>
</header>
