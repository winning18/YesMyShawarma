<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Stock') }}</h2>
            <a href="{{ route('dashboard.stock.create') }}" class="px-3 py-1.5 bg-gray-800 text-white text-sm font-semibold rounded-md hover:bg-gray-900">
                {{ __('Add stock item') }}
            </a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @if (session('status'))
                <div class="rounded-md bg-green-50 text-green-700 text-sm px-4 py-2">{{ session('status') }}</div>
            @endif

            <div class="bg-white shadow rounded-lg overflow-hidden overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-left text-gray-500">
                        <tr>
                            <th class="px-4 py-2">{{ __('Name') }}</th>
                            @if ($showBranchColumn)
                                <th class="px-4 py-2">{{ __('Branch') }}</th>
                            @endif
                            <th class="px-4 py-2 text-right">{{ __('Quantity') }}</th>
                            <th class="px-4 py-2 text-right">{{ __('Low-stock limit') }}</th>
                            <th class="px-4 py-2">{{ __('Added by') }}</th>
                            <th class="px-4 py-2"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($items as $item)
                            <tr>
                                <td class="px-4 py-2 font-medium text-gray-800">
                                    {{ $item->name }}
                                    @if ($item->isLowStock())
                                        <span class="text-xs px-2 py-0.5 rounded-md bg-red-50 text-red-700 align-middle">{{ __('Low stock') }}</span>
                                    @endif
                                </td>
                                @if ($showBranchColumn)
                                    <td class="px-4 py-2 text-gray-500">{{ $item->branch->name }}</td>
                                @endif
                                <td class="px-4 py-2 text-right text-gray-800">{{ $item->quantity }} {{ $item->unit }}</td>
                                <td class="px-4 py-2 text-right text-gray-500">{{ $item->low_stock_threshold }} {{ $item->unit }}</td>
                                <td class="px-4 py-2 text-gray-500">{{ $item->createdBy->name }}</td>
                                <td class="px-4 py-2 text-right">
                                    <a href="{{ route('dashboard.stock.history', $item) }}" class="text-gray-600 hover:underline">{{ __('History') }}</a>
                                    <a href="{{ route('dashboard.stock.edit', $item) }}" class="text-gray-600 hover:underline ml-3">{{ __('Edit') }}</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ $showBranchColumn ? 6 : 5 }}" class="px-4 py-6 text-center text-gray-500">{{ __('No stock items yet.') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
