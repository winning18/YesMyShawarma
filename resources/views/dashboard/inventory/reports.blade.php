{{-- Stock → Stock reports (R4): profit by product, stock value, and what's not selling, at this branch. --}}
@php
    $money = fn (int $p) => 'GH₵'.number_format($p / 100, 2);
    $num = fn ($q) => rtrim(rtrim(number_format((float) $q, 2, '.', ''), '0'), '.');
@endphp
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Stock reports') }}</h2>
        <p class="text-sm text-gray-500">{{ $branch->name }}</p>
        <div class="mt-2"><x-branch-switcher /></div>
    </x-slot>
    <div class="max-w-6xl mx-auto py-8 px-4 space-y-8">
        <form method="GET" class="flex flex-wrap items-end gap-3 text-sm">
            <label>{{ __('From') }} <input type="date" name="from" value="{{ $from->format('Y-m-d') }}" class="block rounded-md border-gray-300 text-sm"></label>
            <label>{{ __('To') }} <input type="date" name="to" value="{{ $to->format('Y-m-d') }}" class="block rounded-md border-gray-300 text-sm"></label>
            <label>{{ __('Not sold for (days)') }} <input type="number" name="days" min="1" max="365" value="{{ $days }}" class="block w-24 rounded-md border-gray-300 text-sm"></label>
            <button class="px-3 py-2 bg-gray-800 text-white font-semibold rounded-md">{{ __('Show') }}</button>
        </form>

        <section class="space-y-2">
            <h3 class="font-semibold text-gray-800">{{ __('Profit by product') }}</h3>
            <div class="bg-white shadow rounded-lg overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="text-left text-gray-500 border-b border-gray-100">
                        <tr><th class="px-4 py-3 font-medium">{{ __('Product') }}</th><th class="px-4 py-3 font-medium text-right">{{ __('Units sold') }}</th><th class="px-4 py-3 font-medium text-right">{{ __('Sales') }}</th><th class="px-4 py-3 font-medium text-right">{{ __('Returned') }}</th><th class="px-4 py-3 font-medium text-right">{{ __('Cost') }}</th><th class="px-4 py-3 font-medium text-right">{{ __('Profit') }}</th></tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($profit as $row)
                            <tr>
                                <td class="px-4 py-2">{{ $row->name }} @if ($row->missing_cost)<span class="text-xs text-amber-700" title="{{ __('Some of these were sold with no cost price set, so their cost is missing.') }}">{{ __('(cost missing on some)') }}</span>@endif</td>
                                <td class="px-4 py-2 text-right tabular-nums">{{ $row->units }}</td>
                                <td class="px-4 py-2 text-right tabular-nums">{{ $money($row->sales) }}</td>
                                <td class="px-4 py-2 text-right tabular-nums">{{ $row->returned ? '-'.$money($row->returned) : '' }}</td>
                                <td class="px-4 py-2 text-right tabular-nums">{{ $money($row->cost) }}</td>
                                <td class="px-4 py-2 text-right tabular-nums font-semibold {{ $row->profit < 0 ? 'text-red-700' : '' }}">{{ $money($row->profit) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-4 py-8 text-center text-gray-500">{{ __('No sales in these dates.') }}</td></tr>
                        @endforelse
                    </tbody>
                    @if ($profit->isNotEmpty())
                        <tfoot class="border-t border-gray-200 font-semibold">
                            <tr><td class="px-4 py-2">{{ __('Total') }}</td><td></td><td class="px-4 py-2 text-right">{{ $money($profit->sum('sales')) }}</td><td class="px-4 py-2 text-right">-{{ $money($profit->sum('returned')) }}</td><td class="px-4 py-2 text-right">{{ $money($profit->sum('cost')) }}</td><td class="px-4 py-2 text-right">{{ $money($profit->sum('profit')) }}</td></tr>
                        </tfoot>
                    @endif
                </table>
            </div>
        </section>

        <section class="space-y-2">
            <h3 class="font-semibold text-gray-800">{{ __('Stock value') }} <span class="font-normal text-gray-500">{{ $money($value->sum('value')) }}</span></h3>
            <p class="text-xs text-gray-500">{{ __('What\'s on the shelf now, at the latest cost price. Products with no cost price count as 0.') }}</p>
            <div class="bg-white shadow rounded-lg overflow-x-auto max-h-96">
                <table class="w-full text-sm">
                    <thead class="text-left text-gray-500 border-b border-gray-100"><tr><th class="px-4 py-3 font-medium">{{ __('Product') }}</th><th class="px-4 py-3 font-medium text-right">{{ __('In stock') }}</th><th class="px-4 py-3 font-medium text-right">{{ __('Cost each') }}</th><th class="px-4 py-3 font-medium text-right">{{ __('Value') }}</th></tr></thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($value as $row)
                            <tr><td class="px-4 py-2">{{ $row->name }}</td><td class="px-4 py-2 text-right">{{ $num($row->quantity) }} {{ $row->unit }}</td><td class="px-4 py-2 text-right">{{ $row->unit_cost === null ? '—' : $money($row->unit_cost) }}</td><td class="px-4 py-2 text-right">{{ $money($row->value) }}</td></tr>
                        @empty
                            <tr><td colspan="4" class="px-4 py-8 text-center text-gray-500">{{ __('No stock on hand.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section class="space-y-2">
            <h3 class="font-semibold text-gray-800">{{ __('Not selling') }}</h3>
            <p class="text-xs text-gray-500">{{ __('In stock, and not sold in the last :days days.', ['days' => $days]) }}</p>
            <div class="bg-white shadow rounded-lg overflow-x-auto max-h-96">
                <table class="w-full text-sm">
                    <thead class="text-left text-gray-500 border-b border-gray-100"><tr><th class="px-4 py-3 font-medium">{{ __('Product') }}</th><th class="px-4 py-3 font-medium text-right">{{ __('In stock') }}</th><th class="px-4 py-3 font-medium">{{ __('Last sold') }}</th></tr></thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($notSelling as $row)
                            <tr><td class="px-4 py-2">{{ $row->name }}</td><td class="px-4 py-2 text-right">{{ $num($row->quantity) }} {{ $row->unit }}</td><td class="px-4 py-2">{{ $row->last_sold ? \Illuminate\Support\Carbon::parse($row->last_sold)->timezone('Africa/Accra')->format('j M Y') : __('Never') }}</td></tr>
                        @empty
                            <tr><td colspan="3" class="px-4 py-8 text-center text-gray-500">{{ __('Everything in stock has sold recently.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</x-app-layout>
