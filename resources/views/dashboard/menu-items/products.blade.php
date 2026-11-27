{{--
    Products (R2, 2026-11-27): a pharmacy's or shop's product list, a
    searchable table 50 a page (ProductCatalogService::search()). A
    restaurant's menu keeps its cards (index.blade.php).
--}}
@php $cedis = fn (?int $pesewas) => $pesewas === null ? '—' : 'GH₵'.number_format($pesewas / 100, 2); @endphp
<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap justify-between items-center gap-3">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Menu') }}</h2>
                <p class="text-sm text-gray-500">{{ $branch->name }}</p>
                <div class="mt-2"><x-branch-switcher /></div>
            </div>
            @if ($canEditContent)
                <div class="flex gap-2">
                    <a href="{{ route('dashboard.products.import') }}" class="px-3 py-1.5 border border-gray-300 text-sm font-semibold rounded-md hover:bg-gray-50">{{ __('Import') }}</a>
                    <a href="{{ route('dashboard.menu-items.create') }}" class="px-3 py-1.5 bg-gray-800 text-white text-sm font-semibold rounded-md hover:bg-gray-900">{{ __('Add product') }}</a>
                </div>
            @endif
        </div>
    </x-slot>

    <div class="max-w-6xl mx-auto py-8 px-4 space-y-4">
        @if (session('status'))
            <div class="rounded-md bg-green-50 text-green-700 text-sm px-4 py-2">{{ session('status') }}</div>
        @endif

        <form method="GET" class="flex flex-wrap gap-2">
            <input type="search" name="q" value="{{ request('q') }}" autofocus placeholder="{{ __('Search by name, product code or barcode (or scan)') }}" class="flex-1 min-w-[16rem] rounded-md border-gray-300 text-sm">
            <select name="category" class="rounded-md border-gray-300 text-sm" onchange="this.form.submit()">
                <option value="">{{ __('All categories') }}</option>
                @foreach ($categories as $category)
                    <option value="{{ $category->id }}" @selected((int) request('category') === $category->id)>{{ $category->name }}</option>
                @endforeach
            </select>
            <button class="px-3 py-1.5 bg-gray-800 text-white text-sm font-semibold rounded-md hover:bg-gray-900">{{ __('Search') }}</button>
        </form>

        <div class="bg-white shadow rounded-lg overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="text-left text-gray-500 border-b border-gray-100">
                    <tr>
                        <th class="px-4 py-3 font-medium">{{ __('Product') }}</th>
                        <th class="px-4 py-3 font-medium">{{ __('Code') }}</th>
                        <th class="px-4 py-3 font-medium">{{ __('Barcode') }}</th>
                        <th class="px-4 py-3 font-medium text-right">{{ __('Price') }}</th>
                        <th class="px-4 py-3 font-medium text-right">{{ __('Cost') }}</th>
                        <th class="px-4 py-3 font-medium">{{ __('Packs') }}</th>
                        <th class="px-4 py-3 font-medium">{{ __('At this branch') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($products as $item)
                        @php $available = $item->branches->first()?->pivot?->is_available ?? true; @endphp
                        <tr>
                            <td class="px-4 py-2">
                                @if ($canEditContent)
                                    <a href="{{ route('dashboard.menu-items.edit', $item) }}" class="font-semibold text-gray-800 hover:underline">{{ $item->name }}</a>
                                @else
                                    <span class="font-semibold text-gray-800">{{ $item->name }}</span>
                                @endif
                                @unless ($item->is_active)<span class="ml-1 text-xs px-2 py-0.5 rounded-md bg-gray-100 text-gray-500">{{ __('Inactive') }}</span>@endunless
                                <span class="block text-xs text-gray-500">{{ $item->category?->name }}@if ($item->unit_name) · {{ __('per :unit', ['unit' => $item->unit_name]) }}@endif</span>
                            </td>
                            <td class="px-4 py-2 font-mono text-xs">{{ $item->sku }}</td>
                            <td class="px-4 py-2 font-mono text-xs">{{ $item->barcode }}</td>
                            <td class="px-4 py-2 text-right tabular-nums">{{ $cedis($item->base_price) }}</td>
                            <td class="px-4 py-2 text-right tabular-nums text-gray-500">{{ $cedis($item->cost_price) }}</td>
                            <td class="px-4 py-2">{{ $item->packs_count ?: '' }}</td>
                            <td class="px-4 py-2">
                                @if ($canToggleAvailability)
                                    <form method="POST" action="{{ route('dashboard.menu-items.toggle-availability', $item) }}">
                                        @csrf
                                        <button type="submit" class="text-xs px-2 py-1 rounded-md {{ $available ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-700' }}">{{ $available ? __('Available') : __('Sold out') }}</button>
                                    </form>
                                @else
                                    {{ $available ? __('Available') : __('Sold out') }}
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-4 py-10 text-center text-gray-500">
                            @if (request('q') || request('category'))
                                {{ __('No menu items match your search.') }}
                            @else
                                {{ __('No menu items yet.') }} @if ($canEditContent)<a href="{{ route('dashboard.products.import') }}" class="text-indigo-600 hover:underline">{{ __('Import your product list') }}</a>@endif
                            @endif
                        </td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $products->links() }}
    </div>
</x-app-layout>
