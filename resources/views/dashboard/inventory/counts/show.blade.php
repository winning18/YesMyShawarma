{{-- A stock count: enter what's on the shelf, save as you go, finish to correct stock (R4). --}}
@php $num = fn ($q) => $q === null ? '' : rtrim(rtrim(number_format((float) $q, 2, '.', ''), '0'), '.'); $open = $count->status === 'open'; @endphp
<x-app-layout>
    <x-slot name="header"><h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Stock count') }} · {{ $count->created_at->timezone('Africa/Accra')->format('j M Y') }}</h2></x-slot>
    <div class="max-w-4xl mx-auto py-8 px-4 space-y-4">
        @include('dashboard.inventory.partials.flash')
        @if ($open)<p class="text-sm text-gray-600">{{ __('Type what\'s on the shelf for each product you count. Leave a product blank to leave it as it is. Sales made while you count are allowed for.') }}</p>@endif
        <form method="POST" action="{{ route('dashboard.inventory.counts.update', $count) }}" class="bg-white shadow rounded-lg p-4 space-y-4">
            @csrf @method('PUT')
            <input type="search" placeholder="{{ __('Find a product on this list') }}" oninput="const q=this.value.toLowerCase();document.querySelectorAll('[data-count-row]').forEach(r=>r.hidden=q&&!r.dataset.countRow.includes(q))" class="w-full rounded-md border-gray-300 text-sm">
            <table class="w-full text-sm">
                <thead class="text-left text-gray-500 border-b border-gray-100"><tr><th class="py-2 font-medium">{{ __('Product') }}</th><th class="py-2 font-medium text-right">{{ __('System had') }}</th><th class="py-2 font-medium">{{ __('Counted') }}</th><th class="py-2 font-medium text-right">{{ __('Difference') }}</th></tr></thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach ($lines as $line)
                        @php $diff = $line->counted === null ? null : (float) $line->counted - (float) $line->expected; @endphp
                        <tr data-count-row="{{ mb_strtolower($line->stockItem->name) }}">
                            <td class="py-2">{{ $line->stockItem->name }} <span class="text-xs text-gray-500">{{ $line->stockItem->unit }}</span></td>
                            <td class="py-2 text-right tabular-nums">{{ $num($line->expected) }}</td>
                            <td class="py-2 pl-3">
                                @if ($open)
                                    <input type="number" min="0" step="any" name="counted[{{ $line->id }}]" value="{{ $num($line->counted) }}" class="w-24 rounded-md text-sm {{ $errors->has('counted.'.$line->id) ? 'border-red-600 ring-1 ring-red-600' : 'border-gray-300' }}">
                                @else
                                    {{ $num($line->counted) ?: '—' }}
                                @endif
                            </td>
                            <td class="py-2 text-right tabular-nums {{ $diff !== null && $diff < 0 ? 'text-red-700' : '' }}">{{ $diff === null ? '' : (($diff > 0 ? '+' : '').$num($diff)) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            @if ($open)
                <div class="flex flex-wrap items-center gap-3 sticky bottom-0 bg-white py-2">
                    <button class="px-4 py-2 border border-gray-300 text-sm font-semibold rounded-md hover:bg-gray-50">{{ __('Save') }}</button>
                    <button name="finish" value="1" onclick="return confirm(@js(__('Finish the count? Stock is set to what you counted for every product you filled in.')))" class="px-4 py-2 bg-gray-800 text-white text-sm font-semibold rounded-md hover:bg-gray-900">{{ __('Finish: correct stock') }}</button>
                </div>
            @endif
        </form>
        @if ($open)
            <form method="POST" action="{{ route('dashboard.inventory.counts.cancel', $count) }}" onsubmit="return confirm(@js(__('Cancel this count? Nothing changes.')))">@csrf<button class="text-sm text-red-600 hover:underline">{{ __('Cancel count') }}</button></form>
        @endif
    </div>
</x-app-layout>
