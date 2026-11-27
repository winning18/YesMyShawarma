{{-- Stock → Controlled drugs register (R5; the format to confirm with the pilot pharmacist). Printable. --}}
@php $num = fn ($q) => $q === null ? '' : rtrim(rtrim(number_format((float) $q, 2, '.', ''), '0'), '.'); @endphp
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Controlled drugs register') }}</h2>
        <p class="text-sm text-gray-500">{{ config('app.name') }} · {{ $branch->name }}</p>
    </x-slot>
    <div class="max-w-6xl mx-auto py-8 px-4 space-y-4">
        @if ($medicines->isEmpty())
            <p class="bg-white shadow rounded-lg px-4 py-10 text-center text-sm text-gray-500">{{ __('No medicine is marked as a controlled drug. Set Medicine class to Controlled on the product to keep its register.') }}</p>
        @else
            <form method="GET" class="flex flex-wrap items-end gap-3 text-sm print:hidden">
                <label>{{ __('Medicine') }}
                    <select name="medicine" class="block rounded-md border-gray-300 text-sm">
                        @foreach ($medicines as $m)<option value="{{ $m->id }}" @selected($medicine?->id === $m->id)>{{ $m->name }} {{ $m->strength }}</option>@endforeach
                    </select>
                </label>
                <label>{{ __('From') }} <input type="date" name="from" value="{{ $from->format('Y-m-d') }}" class="block rounded-md border-gray-300 text-sm"></label>
                <label>{{ __('To') }} <input type="date" name="to" value="{{ $to->format('Y-m-d') }}" class="block rounded-md border-gray-300 text-sm"></label>
                <button class="px-3 py-2 bg-gray-800 text-white font-semibold rounded-md">{{ __('Show') }}</button>
                <button type="button" onclick="window.print()" class="px-3 py-2 border border-gray-300 font-semibold rounded-md">{{ __('Print') }}</button>
            </form>
            <h3 class="font-semibold text-gray-800">{{ $medicine->name }} {{ $medicine->strength }} {{ $medicine->dosage_form }} <span class="font-normal text-gray-500">· {{ $from->format('j M Y') }} – {{ $to->format('j M Y') }} · {{ __('in :unit', ['unit' => $medicine->unit_name ?: __('units')]) }}</span></h3>
            <div class="bg-white shadow rounded-lg overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="text-left text-gray-500 border-b border-gray-100"><tr><th class="px-3 py-2 font-medium">{{ __('Date') }}</th><th class="px-3 py-2 font-medium">{{ __('Entry') }}</th><th class="px-3 py-2 font-medium">{{ __('Reference') }}</th><th class="px-3 py-2 font-medium">{{ __('Details') }}</th><th class="px-3 py-2 font-medium text-right">{{ __('In') }}</th><th class="px-3 py-2 font-medium text-right">{{ __('Out') }}</th><th class="px-3 py-2 font-medium text-right">{{ __('Balance') }}</th><th class="px-3 py-2 font-medium">{{ __('Approved / done by') }}</th></tr></thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr class="bg-gray-50"><td class="px-3 py-2" colspan="6">{{ __('Balance brought forward') }}</td><td class="px-3 py-2 text-right font-semibold">{{ $num($register['opening']) }}</td><td></td></tr>
                        @forelse ($register['rows'] as $row)
                            <tr>
                                <td class="px-3 py-2 whitespace-nowrap">{{ $row['at']->timezone('Africa/Accra')->format('j M Y, H:i') }}</td>
                                <td class="px-3 py-2">{{ $row['what'] }}</td>
                                <td class="px-3 py-2 font-mono text-xs">{{ $row['reference'] }}</td>
                                <td class="px-3 py-2 text-gray-700">{{ $row['details'] }}</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ $num($row['in']) }}</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ $num($row['out']) }}</td>
                                <td class="px-3 py-2 text-right tabular-nums font-semibold">{{ $num($row['balance']) }}</td>
                                <td class="px-3 py-2">{{ $row['by'] }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="px-3 py-8 text-center text-gray-500">{{ __('Nothing received, sold or moved in these dates.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <p class="text-xs text-gray-500 print:hidden">{{ __('Built from the stock record, which can\'t be edited. The details required by the Pharmacy Council are to be confirmed with your pharmacist.') }}</p>
        @endif
    </div>
</x-app-layout>
