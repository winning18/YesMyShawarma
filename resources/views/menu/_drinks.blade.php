{{-- Drinks — optional add-ons, only rendered when any are available --}}
@if ($drinks->isNotEmpty())
    <section>
        <h2 class="text-xl font-bold mb-4">{{ __('Drinks') }}</h2>

        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-4">
            @foreach ($drinks as $drink)
                <div class="border border-brand-gray-100 rounded-lg p-3 text-center">
                    <x-product-image :item="$drink" class="w-full aspect-square rounded-md mb-2" />
                    <p class="text-sm font-semibold truncate">{{ $drink->name }}</p>
                    <p class="text-sm text-brand-gray-500 mb-2">
                        GH₵{{ number_format($drink->base_price / 100, 2) }}
                    </p>
                    <form method="POST" action="{{ route('cart.add') }}">
                        @csrf
                        <input type="hidden" name="branch_id" value="{{ $branch->id }}">
                        <input type="hidden" name="menu_item_id" value="{{ $drink->id }}">
                        <input type="hidden" name="quantity" value="1">
                        <button type="submit" class="w-full px-3 py-1.5 bg-brand-surface text-brand-on-surface text-xs font-semibold rounded-md hover:opacity-90">
                            {{ __('+ Add') }}
                        </button>
                    </form>
                </div>
            @endforeach
        </div>
    </section>
@endif
