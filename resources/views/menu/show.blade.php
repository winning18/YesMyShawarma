<x-customer-layout
    :title="$metaTitle"
    :og-image="$item->imageUrl()"
    :og-description="$metaDescription"
>
    {{-- JSON_HEX_TAG/JSON_HEX_AMP: $productSchema embeds staff-editable
         item name/description verbatim (aeo.md) — without these, a name or
         description containing a literal </script> would close this tag
         early and let whatever follows execute as a new <script>, a stored
         XSS reachable by anyone with menu.edit_content. --}}
    <script type="application/ld+json">{!! json_encode($productSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>

    <a href="{{ route('menu.index') }}" class="text-sm underline text-brand-gray-500">&larr; {{ __('Back to menu') }}</a>

    <x-customer-branch-bar :branch="$branch" class="mt-4" />

    <x-error-summary class="mt-6" />

    {{-- The selected product --}}
    <div class="grid grid-cols-1 md:grid-cols-2 gap-10 mt-6 mb-12">
        <x-product-image :item="$item" class="w-full aspect-square rounded-xl" />

        <div>
            <h1 class="text-2xl font-bold mb-2">{{ $item->name }}</h1>

            @if ($popularRank)
                <p class="inline-block mb-3 bg-brand-yellow text-brand-on-primary text-xs font-bold uppercase tracking-wide px-2 py-1 rounded">
                    {{ $popularRank === 1 ? __('Most ordered this month') : __('#:rank most ordered this month', ['rank' => $popularRank]) }}
                </p>
            @endif

            @if ($item->description)
                <p class="text-brand-gray-500 mb-4">{{ $item->description }}</p>
            @endif

            <p class="text-2xl font-bold mb-6">
                GH₵{{ number_format($item->base_price / 100, 2) }}
            </p>

            @include('menu._item-form')
        </div>
    </div>

    @include('menu._drinks')

    @include('partials.menu-item-form-script')
</x-customer-layout>
