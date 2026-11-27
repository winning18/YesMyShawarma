{{-- A purchase order: its lines, marking it ordered, receiving it (R4). --}}
@php $money = fn (int $p) => 'GH₵'.number_format($p / 100, 2); @endphp
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight"><span class="font-mono">{{ $order->reference }}</span> · {{ $statusLabel }}</h2>
        <p class="text-sm text-gray-500">{{ $order->supplier?->name ?? __('No supplier') }} · {{ __('by :name, :date', ['name' => $order->creator?->name, 'date' => $order->created_at->timezone('Africa/Accra')->format('j M Y')]) }}@if ($order->received_at) · {{ __('received :date by :name', ['date' => $order->received_at->timezone('Africa/Accra')->format('j M Y'), 'name' => $order->receiver?->name]) }}@endif</p>
    </x-slot>
    <div class="max-w-4xl mx-auto py-8 px-4 space-y-4">
        @include('dashboard.inventory.partials.flash')
        <form method="POST" action="{{ route('dashboard.inventory.purchase-orders.receive', $order) }}" class="bg-white shadow rounded-lg p-6 space-y-4">
            @csrf
            <table class="w-full text-sm">
                <thead class="text-left text-gray-500 border-b border-gray-100"><tr><th class="py-2 font-medium">{{ __('Product') }}</th><th class="py-2 font-medium text-right">{{ __('Ordered') }}</th><th class="py-2 font-medium text-right">{{ __('Received') }}</th><th class="py-2 font-medium text-right">{{ __('Cost each') }}</th>@if ($order->isOpen())<th class="py-2 font-medium">{{ __('Arrived now') }}</th>@endif</tr></thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach ($order->items as $line)
                        <tr>
                            <td class="py-2">{{ $lineName($line) }}</td>
                            <td class="py-2 text-right">{{ $line->quantity }}</td>
                            <td class="py-2 text-right">{{ $line->quantity_received }}</td>
                            <td class="py-2 text-right">{{ $money($line->unit_cost) }}</td>
                            @if ($order->isOpen())
                                <td class="py-2 pl-3"><input type="number" min="0" max="{{ $line->outstanding() }}" name="received[{{ $line->id }}]" value="{{ $line->outstanding() }}" @disabled($line->outstanding() === 0) class="w-24 rounded-md border-gray-300 text-sm"></td>
                            @endif
                        </tr>
                    @endforeach
                </tbody>
                <tfoot><tr class="border-t border-gray-200 font-semibold"><td class="py-2">{{ __('Total') }}</td><td></td><td></td><td class="py-2 text-right">{{ $money($order->total) }}</td>@if ($order->isOpen())<td></td>@endif</tr></tfoot>
            </table>
            @if ($order->note)<p class="text-sm text-gray-600">{{ $order->note }}</p>@endif
            @if ($order->isOpen())
                <div class="flex flex-wrap items-center gap-3">
                    <x-primary-button>{{ __('Receive: add to stock') }}</x-primary-button>
                    <span class="text-xs text-gray-500">{{ __('Change a number if less arrived; the rest stays on order.') }}</span>
                </div>
            @endif
        </form>
        <div class="flex flex-wrap gap-3">
            @if ($order->status === \App\Models\PurchaseOrder::DRAFT)
                <form method="POST" action="{{ route('dashboard.inventory.purchase-orders.ordered', $order) }}">@csrf<button class="px-3 py-2 border border-gray-300 text-sm font-semibold rounded-md hover:bg-gray-50">{{ __('Mark as ordered') }}</button></form>
            @endif
            @if (in_array($order->status, [\App\Models\PurchaseOrder::DRAFT, \App\Models\PurchaseOrder::ORDERED], true))
                <form method="POST" action="{{ route('dashboard.inventory.purchase-orders.cancel', $order) }}" onsubmit="return confirm(@js(__('Cancel this purchase order?')))">@csrf<button class="px-3 py-2 text-sm text-red-600 hover:underline">{{ __('Cancel order') }}</button></form>
            @endif
            <a href="{{ route('dashboard.inventory.purchase-orders.index') }}" class="px-3 py-2 text-sm text-gray-600 hover:underline">{{ __('Back to purchase orders') }}</a>
        </div>
    </div>
</x-app-layout>
