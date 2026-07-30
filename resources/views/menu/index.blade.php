<x-customer-layout title="Menu · {{ config('app.name') }}" body-class="menu-hero-bg">
    <div class="text-center mb-6">
        <h1 class="text-2xl font-bold">{{ __('Menu') }} — {{ $branch->name }}</h1>
        <a href="{{ route('branches.index') }}" class="text-sm underline text-brand-gray-500">{{ __('Change branch') }}</a>
    </div>

    @if ($errors->any())
        <div class="mb-6 rounded-lg bg-brand-red-light border border-brand-red text-brand-red-dark text-sm px-4 py-2">
            {{ $errors->first() }}
        </div>
    @endif

    {{--
        Side-by-side (compact, fixed-size thumbnail) for Drinks only,
        stacked (image on top, full card width) for everything else,
        including Hot Dogs/Loaded Fries — an explicit per-request choice,
        not a rule derived from anything about these categories. Side-by-
        side was previously abandoned everywhere because tying the
        square's size to the text column's height created a feedback loop
        for long, multi-line names — but a FIXED size (not measured/
        matched at all) never had that problem; it's what's used here.
    --}}
    @php $sideBySideCategories = ['drinks']; @endphp

    @foreach ($categories as $group)
        <section class="mb-10">
            <h2 class="text-lg font-bold mb-4">{{ $group['category']->name }}</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                @foreach ($group['items'] as $item)
                    @if (in_array($group['category']->slug, $sideBySideCategories, true))
                        <div class="bg-brand-black rounded-lg p-4 flex items-start gap-3">
                            <a href="{{ route('menu.show', $item) }}" class="shrink-0">
                                <x-product-image :item="$item" class="w-20 h-20" />
                            </a>

                            <div class="flex-1 min-w-0">
                                @include('menu._item-details', ['item' => $item, 'branch' => $branch])
                            </div>
                        </div>
                    @else
                        <div class="bg-brand-black rounded-lg p-4">
                            <a href="{{ route('menu.show', $item) }}">
                                <x-product-image :item="$item" class="w-full max-w-56 mx-auto aspect-square mb-4" />
                            </a>

                            @include('menu._item-details', ['item' => $item, 'branch' => $branch])
                        </div>
                    @endif
                @endforeach
            </div>
        </section>
    @endforeach
</x-customer-layout>
