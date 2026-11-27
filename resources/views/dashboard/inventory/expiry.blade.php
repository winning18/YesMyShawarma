{{-- Stock → Expiry (R5): batches expired or expiring soon at this branch; an expired one is written off. --}}
@php $num = fn ($q) => rtrim(rtrim(number_format((float) $q, 2, '.', ''), '0'), '.'); $today = now('Africa/Accra')->startOfDay(); @endphp
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Expiry') }}</h2>
        <p class="text-sm text-gray-500">{{ $branch->name }}</p>
        <div class="mt-2"><x-branch-switcher /></div>
    </x-slot>
    <div class="max-w-5xl mx-auto py-8 px-4 space-y-4">
        @include('dashboard.inventory.partials.flash')
        <form method="GET" class="flex items-end gap-2 text-sm">
            <label>{{ __('Expiring within (days)') }} <input type="number" name="days" min="1" max="730" value="{{ $days }}" class="block w-24 rounded-md border-gray-300 text-sm"></label>
            <button class="px-3 py-2 bg-gray-800 text-white font-semibold rounded-md">{{ __('Show') }}</button>
        </form>
        <div class="bg-white shadow rounded-lg overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="text-left text-gray-500 border-b border-gray-100"><tr><th class="px-4 py-3 font-medium">{{ __('Medicine') }}</th><th class="px-4 py-3 font-medium">{{ __('Batch') }}</th><th class="px-4 py-3 font-medium">{{ __('Expiry') }}</th><th class="px-4 py-3 font-medium text-right">{{ __('Left') }}</th><th></th></tr></thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($batches as $batch)
                        @php $expired = $batch->expiry_date->lt($today); @endphp
                        <tr class="{{ $expired ? 'bg-red-50' : '' }}">
                            <td class="px-4 py-2">{{ $batch->stockItem->menuItem?->name }}</td>
                            <td class="px-4 py-2 font-mono text-xs">{{ $batch->batch_number ?? '—' }}</td>
                            <td class="px-4 py-2 {{ $expired ? 'text-red-700 font-semibold' : '' }}">{{ $batch->expiry_date->format('j M Y') }} <span class="text-xs">{{ $expired ? __('(expired)') : '('.__(':n days', ['n' => $today->diffInDays($batch->expiry_date)]).')' }}</span></td>
                            <td class="px-4 py-2 text-right tabular-nums">{{ $num($batch->quantity) }} {{ $batch->stockItem->unit }}</td>
                            <td class="px-4 py-2 text-right">
                                @if ($expired)
                                    <form method="POST" action="{{ route('dashboard.inventory.batches.write-off', $batch) }}" onsubmit="return confirm(@js(__('Write off what\'s left of this batch? Stock goes down by that much.')))">@csrf<button class="text-xs text-red-700 font-semibold hover:underline">{{ __('Write off') }}</button></form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-10 text-center text-gray-500">{{ __('Nothing expires in the next :days days.', ['days' => $days]) }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <p class="text-xs text-gray-500">{{ __('Expired stock can\'t be sold. The batch that expires first is always sold first. The owner gets a text about this list every Monday.') }}</p>
    </div>
</x-app-layout>
