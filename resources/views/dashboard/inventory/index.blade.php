{{-- Stock → Stock levels (R4): each product's stock at this branch, with its low-stock level. --}}
@php $num = fn ($q) => rtrim(rtrim(number_format((float) $q, 2, '.', ''), '0'), '.'); @endphp
<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap justify-between items-center gap-3">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Stock levels') }}</h2>
                <p class="text-sm text-gray-500">{{ $branch->name }}</p>
                <div class="mt-2"><x-branch-switcher /></div>
            </div>
            <div class="flex gap-2">
                <a href="{{ route('dashboard.inventory.purchase-orders.create', ['receive' => 1]) }}" class="px-3 py-1.5 bg-gray-800 text-white text-sm font-semibold rounded-md hover:bg-gray-900">{{ __('Receive stock') }}</a>
                <a href="{{ route('dashboard.inventory.counts.index') }}" class="px-3 py-1.5 border border-gray-300 text-sm font-semibold rounded-md hover:bg-gray-50">{{ __('Count stock') }}</a>
            </div>
        </div>
    </x-slot>

    <div class="max-w-6xl mx-auto py-8 px-4 space-y-4">
        @include('dashboard.inventory.partials.flash')
        <form method="GET" class="flex flex-wrap gap-2">
            <input type="search" name="q" value="{{ request('q') }}" placeholder="{{ __('Search by name, product code or barcode (or scan)') }}" class="flex-1 min-w-[16rem] rounded-md border-gray-300 text-sm">
            <select name="category" class="rounded-md border-gray-300 text-sm" onchange="this.form.submit()">
                <option value="">{{ __('All categories') }}</option>
                @foreach ($categories as $category)
                    <option value="{{ $category->id }}" @selected((int) request('category') === $category->id)>{{ $category->name }}</option>
                @endforeach
            </select>
            <button class="px-3 py-1.5 bg-gray-800 text-white text-sm font-semibold rounded-md">{{ __('Search') }}</button>
        </form>

        <div class="bg-white shadow rounded-lg overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="text-left text-gray-500 border-b border-gray-100">
                    <tr>
                        <th class="px-4 py-3 font-medium">{{ __('Product') }}</th>
                        <th class="px-4 py-3 font-medium text-right">{{ __('In stock') }}</th>
                        <th class="px-4 py-3 font-medium">{{ __('Warn below') }}</th>
                        <th class="px-4 py-3 font-medium"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($products as $product)
                        @php $item = $items[$product->id] ?? null; $qty = $item?->quantity ?? 0; $low = $item && $item->isLowStock(); @endphp
                        <tr>
                            <td class="px-4 py-2">
                                <span class="font-semibold text-gray-800">{{ $product->name }}</span>
                                <span class="block text-xs text-gray-500 font-mono">{{ $product->sku }}</span>
                            </td>
                            <td class="px-4 py-2 text-right tabular-nums {{ $qty < 0 || $low ? 'text-red-700 font-semibold' : '' }}">
                                {{ $num($qty) }} <span class="text-xs text-gray-500">{{ $product->unit_name ?: __('unit') }}</span>
                            </td>
                            <td class="px-4 py-2">
                                <form method="POST" action="{{ route('dashboard.inventory.threshold', $product) }}" class="flex items-center gap-1">
                                    @csrf
                                    <input name="low_stock_threshold" type="number" min="0" step="1" value="{{ $num($item?->low_stock_threshold ?? 0) }}" class="w-20 rounded-md border-gray-300 text-xs" aria-label="{{ __('Warn below') }}">
                                    <button class="text-xs text-indigo-600 hover:underline">{{ __('Save') }}</button>
                                </form>
                            </td>
                            <td class="px-4 py-2 text-right"><a href="{{ route('dashboard.inventory.history', $product) }}" class="text-xs text-indigo-600 hover:underline">{{ __('History') }}</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-4 py-10 text-center text-gray-500">{{ __('No products match.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <p class="text-xs text-gray-500">{{ __('Below the warn level, the owner gets an SMS. Below zero means more was sold than the system knew about: count it to put it right.') }}</p>
        {{ $products->links() }}
    </div>
</x-app-layout>
