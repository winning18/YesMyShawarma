@props(['item'])

{{--
    No default width here on purpose — the caller always supplies one (e.g.
    class="w-16"). A previous version baked "w-full" into these defaults,
    and $attributes->merge() just concatenates class lists rather than
    letting one override the other, so both "w-full" and the caller's
    "w-20" ended up in the compiled HTML at once — whichever one Tailwind's
    stylesheet happened to place last in the cascade won, which turned out
    to be w-full, ballooning every placeholder to nearly the card's full
    width and squeezing item names into unreadable narrow columns.
--}}
@if ($item->image_path)
    <img
        src="{{ asset($item->image_path) }}"
        alt="{{ $item->name }}"
        {{ $attributes->merge(['class' => 'aspect-square object-cover rounded-md bg-brand-gray-100']) }}
    >
@else
    {{-- No product photos yet — placeholder keeps the layout identical to
         when real photos are added, so nothing needs to change but this
         image_path check once they exist. --}}
    <div
        {{ $attributes->merge(['class' => 'aspect-square rounded-md bg-brand-gray-100 flex items-center justify-center']) }}
        role="img"
        aria-label="{{ $item->name }}"
    >
        <svg viewBox="0 0 24 24" fill="none" class="w-6 h-6 text-brand-gray-300">
            <path
                d="M4 7a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V7Z"
                stroke="currentColor" stroke-width="1.5"
            />
            <circle cx="9" cy="10.5" r="1.5" stroke="currentColor" stroke-width="1.5" />
            <path d="m5 16 4.5-4 3 2.5L16 11l3 3.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
        </svg>
    </div>
@endif
