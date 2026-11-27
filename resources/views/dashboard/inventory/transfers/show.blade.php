{{-- A transfer: what was sent, and the receiving branch confirming what arrived (R4). --}}
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ $transfer->fromBranch->name }} → {{ $transfer->toBranch->name }}</h2>
        <p class="text-sm text-gray-500">{{ __('Sent :date by :name', ['date' => $transfer->sent_at?->timezone('Africa/Accra')->format('j M Y, H:i'), 'name' => $transfer->sender?->name]) }}@if ($transfer->received_at) · {{ __('received :date by :name', ['date' => $transfer->received_at->timezone('Africa/Accra')->format('j M Y, H:i'), 'name' => $transfer->receiver?->name]) }}@endif @if ($transfer->status === 'cancelled') · {{ __('Cancelled') }}@endif</p>
    </x-slot>
    <div class="max-w-3xl mx-auto py-8 px-4 space-y-4">
        @include('dashboard.inventory.partials.flash')
        <form method="POST" action="{{ route('dashboard.inventory.transfers.receive', $transfer) }}" class="bg-white shadow rounded-lg p-6 space-y-4">
            @csrf
            <table class="w-full text-sm">
                <thead class="text-left text-gray-500 border-b border-gray-100"><tr><th class="py-2 font-medium">{{ __('Product') }}</th><th class="py-2 font-medium text-right">{{ __('Sent') }}</th><th class="py-2 font-medium text-right">{{ $canReceive ? __('Arrived') : __('Received') }}</th></tr></thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach ($transfer->items as $line)
                        <tr>
                            <td class="py-2">{{ $line->menuItem?->name }} <span class="text-xs text-gray-500">{{ $line->menuItem?->unit_name }}</span></td>
                            <td class="py-2 text-right">{{ $line->units }}</td>
                            <td class="py-2 text-right">
                                @if ($canReceive)
                                    <input type="number" min="0" max="{{ $line->units }}" name="received[{{ $line->id }}]" value="{{ $line->units }}" class="w-24 rounded-md border-gray-300 text-sm">
                                @else
                                    {{ $line->received_units ?? '—' }}
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            @if ($transfer->note)<p class="text-sm text-gray-600">{{ $transfer->note }}</p>@endif
            @if ($canReceive)
                <div class="flex flex-wrap items-center gap-3"><x-primary-button>{{ __('Confirm: add to stock here') }}</x-primary-button><span class="text-xs text-gray-500">{{ __('Change a number if less arrived.') }}</span></div>
            @endif
        </form>
        @if ($canCancel)
            <form method="POST" action="{{ route('dashboard.inventory.transfers.cancel', $transfer) }}" onsubmit="return confirm(@js(__('Cancel this transfer? The stock comes back to this branch.')))">@csrf<button class="text-sm text-red-600 hover:underline">{{ __('Cancel transfer') }}</button></form>
        @endif
        <a href="{{ route('dashboard.inventory.transfers.index') }}" class="text-sm text-gray-600 hover:underline">{{ __('Back to transfers') }}</a>
    </div>
</x-app-layout>
