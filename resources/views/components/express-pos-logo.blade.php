{{--
    The Express POS logo (Solusys Digital), horizontal, for dark
    backgrounds: the signal arcs (white dot, Kente Gold, Forest Green,
    Signal Red), the EXPRESS wordmark with the gold POS pill, and "A
    Solusys Digital product" directly under EXPRESS — redrawn from the
    logo system so it stays sharp at any size. Only ever on the sign-in
    pages; a business's customers never see it.
--}}
<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-3 select-none']) }} role="img" aria-label="{{ __('Express POS, a Solusys Digital product') }}">
    <svg viewBox="0 0 34 48" class="h-14 w-auto shrink-0" fill="none" aria-hidden="true">
        <circle cx="5" cy="24" r="4" fill="#FFFFFF" />
        <path d="M11.4 16.9a10 10 0 0 1 0 14.2" stroke="#FCD116" stroke-width="4.5" stroke-linecap="round" />
        <path d="M16.3 11.6a17 17 0 0 1 0 24.8" stroke="#12A150" stroke-width="4.5" stroke-linecap="round" />
        <path d="M21.2 6.3a24 24 0 0 1 0 35.4" stroke="#E23A3A" stroke-width="4.5" stroke-linecap="round" />
    </svg>
    <span class="flex flex-col" aria-hidden="true">
        <span class="inline-flex items-center gap-3">
            <span class="font-xpos-display italic font-black tracking-tight leading-none text-white text-[2rem]">EXPRESS</span>
            <span class="rounded-full bg-xpos-gold text-xpos-midnight font-extrabold tracking-[0.3em] leading-tight text-[0.7rem] px-2.5 py-0.5">POS</span>
        </span>
        <span class="mt-1.5 text-xs text-xpos-muted">{{ __('A Solusys Digital product') }}</span>
    </span>
</span>
