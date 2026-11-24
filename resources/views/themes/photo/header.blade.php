{{-- Photo-led header: light and quiet, so the food photos carry the page. --}}
<header class="bg-brand-white/95 backdrop-blur sticky top-0 z-40 border-b border-brand-gray-100">
    <div class="max-w-6xl mx-auto px-4 h-16 flex items-center justify-between gap-6">
        <a href="{{ route('home') }}" class="shrink-0">
            <x-brand-logo class="h-10 w-auto" text-class="font-heading text-xl text-brand-black" />
        </a>

        @if ($websiteOn)
            <nav class="hidden md:flex items-center gap-6 text-sm font-medium text-brand-gray-500">
                @foreach ($customerNav as $link)
                    @continue($link['url'] === route('home'))
                    <a href="{{ $link['url'] }}" class="hover:text-brand-black transition {{ $link['active'] ? 'text-brand-black underline decoration-2 underline-offset-8 decoration-brand-yellow' : '' }}">{{ $link['label'] }}</a>
                @endforeach
            </nav>

            @include('themes.partials.header-actions', ['orderUrl' => route('menu.index')])
        @endif
    </div>
</header>
