{{--
    A dish as a large photo card (Photo-led). With $branch set and an item
    that has no options, "Add" puts one straight in the cart and comes back
    to $anchor; an item with options (or with no branch known) opens its
    own page instead, where the options are chosen.

    @param  \App\Models\MenuItem  $item
    @param  ?\App\Models\Branch  $branch
    @param  ?string  $anchor
    @param  bool  $isPopular
--}}
@php
    $quickAdd = ($branch ?? null) && $item->relationLoaded('optionGroups') && $item->optionGroups->isEmpty();
@endphp
<article class="group flex flex-col">
    <a href="{{ route('menu.show', $item) }}" class="relative block aspect-[4/3] overflow-hidden rounded-2xl bg-brand-yellow-light">
        @if ($item->imageUrl())
            <x-product-image :item="$item" class="absolute inset-0 w-full h-full rounded-none group-hover:scale-[1.03] transition duration-500" />
        @else
            {{-- No photo yet: a calm tile in the brand's light colour with the dish's initial. --}}
            <span class="absolute inset-0 flex items-center justify-center font-heading text-6xl text-brand-black/25" aria-hidden="true">{{ mb_strtoupper(mb_substr($item->name, 0, 1)) }}</span>
        @endif
        @if ($isPopular ?? false)
            <span class="absolute top-3 left-3 bg-brand-white/95 text-brand-black text-[11px] font-bold uppercase tracking-wide px-2.5 py-1 rounded-full">{{ __('Popular') }}</span>
        @endif
    </a>

    <div class="pt-3 flex-1 flex flex-col">
        <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-x-3 gap-y-0.5">
            <h3 class="font-heading text-base sm:text-lg leading-snug text-brand-black">
                <a href="{{ route('menu.show', $item) }}" class="hover:underline decoration-brand-yellow decoration-2 underline-offset-4">{{ $item->name }}</a>
            </h3>
            <p class="font-semibold whitespace-nowrap text-brand-black">GH₵{{ number_format($item->base_price / 100, 2) }}</p>
        </div>

        @if ($item->description)
            <p class="mt-1 text-sm text-brand-gray-500 line-clamp-2">{{ $item->description }}</p>
        @endif

        <div class="mt-auto pt-3">
            @if ($quickAdd)
                <form method="POST" action="{{ route('cart.add') }}">
                    @csrf
                    <input type="hidden" name="branch_id" value="{{ $branch->id }}">
                    <input type="hidden" name="menu_item_id" value="{{ $item->id }}">
                    <input type="hidden" name="quantity" value="1">
                    @if ($anchor ?? null)
                        <input type="hidden" name="return_anchor" value="{{ $anchor }}">
                    @endif
                    <button type="submit" class="inline-flex items-center gap-1.5 px-4 py-2 rounded-full bg-brand-yellow text-brand-on-primary text-sm font-semibold hover:bg-brand-yellow-dark transition">
                        <span aria-hidden="true">+</span> {{ __('Add') }}
                    </button>
                </form>
            @else
                <a href="{{ route('menu.show', $item) }}" class="inline-flex items-center px-4 py-2 rounded-full border border-brand-black text-brand-black text-sm font-semibold hover:bg-brand-surface hover:text-brand-on-surface transition">
                    {{ ($branch ?? null) ? __('Choose') : __('View') }}
                </a>
            @endif
        </div>
    </div>
</article>
