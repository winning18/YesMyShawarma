{{--
    One-page's menu: the branch being ordered from, a sticky category bar,
    then each category as rows of dishes with a quick Add that comes back
    to the same category. Shared by the home page's Menu section and the
    /menu page.

    @param  ?\App\Models\Branch  $branch
    @param  \Illuminate\Support\Collection  $categories
    @param  list<int>  $popularIds
--}}
@if (! $branch || $categories->isEmpty())
    <p class="rounded-2xl border-2 border-dashed border-brand-gray-300 p-8 text-center text-brand-gray-500">{{ __('The menu is being set up. Please check back soon.') }}</p>
@else
    <x-customer-branch-bar :branch="$branch" class="mb-4" />

    <x-error-summary class="mb-4" />

    @include('themes.partials.category-bar', ['categories' => $categories])

    @foreach ($categories as $group)
        <section id="cat-{{ $group['category']->slug }}" class="scroll-mt-36 pt-8">
            <h3 class="font-heading text-2xl sm:text-3xl text-brand-black mb-4 flex items-baseline gap-3">
                {{ $group['category']->name }}
                <span class="text-sm font-customer font-medium text-brand-gray-500">{{ trans_choice(':count dish|:count dishes', $group['items']->count()) }}</span>
            </h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                @foreach ($group['items'] as $item)
                    @php $quickAdd = $item->optionGroups->isEmpty() && ! $item->needsPrescription(); @endphp
                    <div class="flex gap-4 rounded-2xl border border-brand-gray-100 bg-brand-white p-3 sm:p-4">
                        @if ($item->imageUrl())
                            <a href="{{ route('menu.show', $item) }}" class="relative shrink-0 w-24 h-24 sm:w-28 sm:h-28 overflow-hidden rounded-xl bg-brand-gray-100">
                                <x-product-image :item="$item" class="absolute inset-0 w-full h-full rounded-none" />
                            </a>
                        @endif
                        <div class="flex-1 min-w-0 flex flex-col">
                            <div class="flex items-start justify-between gap-3">
                                <a href="{{ route('menu.show', $item) }}" class="font-semibold text-brand-black hover:underline">
                                    {{ $item->name }}
                                    @if (in_array($item->id, $popularIds, true))
                                        <span class="ms-1 align-middle bg-brand-yellow text-brand-on-primary text-[10px] font-bold uppercase tracking-wide px-2 py-0.5 rounded-full">{{ __('Popular') }}</span>
                                    @endif
                                </a>
                                <span class="font-semibold whitespace-nowrap text-brand-black">GH₵{{ number_format($item->base_price / 100, 2) }}</span>
                            </div>
                            @if ($item->description)
                                <p class="mt-1 text-sm text-brand-gray-500 line-clamp-2">{{ $item->description }}</p>
                            @endif
                            <div class="mt-auto pt-2 flex justify-end">
                                @if ($quickAdd)
                                    <form method="POST" action="{{ route('cart.add') }}">
                                        @csrf
                                        <input type="hidden" name="branch_id" value="{{ $branch->id }}">
                                        <input type="hidden" name="menu_item_id" value="{{ $item->id }}">
                                        <input type="hidden" name="quantity" value="1">
                                        <input type="hidden" name="return_anchor" value="cat-{{ $group['category']->slug }}">
                                        <button type="submit" class="inline-flex items-center gap-1 px-4 py-1.5 rounded-full bg-brand-surface text-brand-on-surface text-sm font-semibold hover:opacity-90 transition" aria-label="{{ __('Add :item to cart', ['item' => $item->name]) }}">
                                            <span aria-hidden="true">+</span> {{ __('Add') }}
                                        </button>
                                    </form>
                                @else
                                    <a href="{{ route('menu.show', $item) }}" class="inline-flex items-center px-4 py-1.5 rounded-full border-2 border-brand-black text-brand-black text-sm font-semibold hover:bg-brand-surface hover:text-brand-on-surface transition">{{ __('Choose') }}</a>
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </section>
    @endforeach
@endif
