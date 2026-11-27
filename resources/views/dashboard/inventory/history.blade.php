{{-- A product's stock history at this branch (R4). --}}
@php
    $num = fn ($q) => rtrim(rtrim(number_format((float) $q, 2, '.', ''), '0'), '.');
    $labels = ['sale' => __('Sold'), 'restock' => __('Back in'), 'received' => __('Received'), 'count' => __('Count'), 'transfer_out' => __('Sent'), 'transfer_in' => __('Arrived'), 'returned' => __('Returned')];
@endphp
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ $product->name }}</h2>
        <p class="text-sm text-gray-500">{{ __('In stock: :qty :unit', ['qty' => $num($item->quantity), 'unit' => $item->unit]) }}</p>
    </x-slot>
    <div class="max-w-4xl mx-auto py-8 px-4 space-y-4">
        <div class="bg-white shadow rounded-lg overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="text-left text-gray-500 border-b border-gray-100">
                    <tr><th class="px-4 py-3 font-medium">{{ __('When') }}</th><th class="px-4 py-3 font-medium">{{ __('What') }}</th><th class="px-4 py-3 font-medium text-right">{{ __('Change') }}</th><th class="px-4 py-3 font-medium">{{ __('By') }}</th><th class="px-4 py-3 font-medium">{{ __('Note') }}</th></tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($movements as $m)
                        @php $signed = $m->type === 'count' ? (float) $m->quantity : (in_array($m->type, \App\Models\StockMovement::OUTGOING, true) ? -1 : 1) * (float) $m->quantity; @endphp
                        <tr>
                            <td class="px-4 py-2 whitespace-nowrap">{{ $m->created_at?->timezone('Africa/Accra')->format('j M Y, H:i') }}</td>
                            <td class="px-4 py-2">{{ $labels[$m->type] ?? $m->type }}</td>
                            <td class="px-4 py-2 text-right tabular-nums {{ $signed < 0 ? 'text-red-700' : 'text-green-700' }}">{{ ($signed > 0 ? '+' : '').$num($signed) }}</td>
                            <td class="px-4 py-2">{{ $m->actor?->name }}</td>
                            <td class="px-4 py-2 text-gray-600">{{ $m->note }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-10 text-center text-gray-500">{{ __('No stock changes yet.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $movements->links() }}
        <a href="{{ route('dashboard.inventory.index') }}" class="text-sm text-gray-600 hover:underline">{{ __('Back to stock levels') }}</a>
    </div>
</x-app-layout>
