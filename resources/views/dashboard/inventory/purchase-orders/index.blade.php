{{-- Stock → Purchase orders (R4). --}}
<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap justify-between items-center gap-3">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Purchase orders') }}</h2>
                <p class="text-sm text-gray-500">{{ $branch->name }}</p>
            </div>
            <div class="flex gap-2">
                <a href="{{ route('dashboard.inventory.purchase-orders.create', ['receive' => 1]) }}" class="px-3 py-1.5 border border-gray-300 text-sm font-semibold rounded-md hover:bg-gray-50">{{ __('Receive without an order') }}</a>
                <a href="{{ route('dashboard.inventory.purchase-orders.create') }}" class="px-3 py-1.5 bg-gray-800 text-white text-sm font-semibold rounded-md hover:bg-gray-900">{{ __('New purchase order') }}</a>
            </div>
        </div>
    </x-slot>
    <div class="max-w-5xl mx-auto py-8 px-4 space-y-4">
        @include('dashboard.inventory.partials.flash')
        <div class="bg-white shadow rounded-lg overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="text-left text-gray-500 border-b border-gray-100"><tr><th class="px-4 py-3 font-medium">{{ __('Order') }}</th><th class="px-4 py-3 font-medium">{{ __('Supplier') }}</th><th class="px-4 py-3 font-medium">{{ __('Status') }}</th><th class="px-4 py-3 font-medium text-right">{{ __('Total') }}</th><th class="px-4 py-3 font-medium">{{ __('Date') }}</th></tr></thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($orders as $order)
                        <tr>
                            <td class="px-4 py-2"><a href="{{ route('dashboard.inventory.purchase-orders.show', $order) }}" class="font-mono font-semibold text-indigo-700 hover:underline">{{ $order->reference }}</a> <span class="text-xs text-gray-500">{{ trans_choice('{1} 1 line|[2,*] :count lines', $order->items_count) }}</span></td>
                            <td class="px-4 py-2">{{ $order->supplier?->name ?? '—' }}</td>
                            <td class="px-4 py-2">{{ $label($order->status) }}</td>
                            <td class="px-4 py-2 text-right tabular-nums">GH₵{{ number_format($order->total / 100, 2) }}</td>
                            <td class="px-4 py-2">{{ $order->created_at->timezone('Africa/Accra')->format('j M Y') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-10 text-center text-gray-500">{{ __('No purchase orders yet.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $orders->links() }}
    </div>
</x-app-layout>
