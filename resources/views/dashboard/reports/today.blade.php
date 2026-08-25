<x-app-layout>
    <x-slot name="header">
        @include('dashboard.reports._tabs', ['active' => 'today'])
    </x-slot>

    <div class="max-w-5xl mx-auto py-8 px-4 space-y-6">
        <div class="flex items-center justify-between flex-wrap gap-3">
            <p class="text-sm text-gray-500">{{ $today->format('l, d F Y') }}</p>

            <div class="flex items-center gap-2">
                <a
                    href="{{ route('dashboard.reports.today.index', ['channel' => 'pos']) }}"
                    class="px-4 py-2 text-sm font-semibold rounded-full {{ $channel === 'pos' ? 'bg-gray-800 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}"
                >{{ __('POS') }}</a>
                <a
                    href="{{ route('dashboard.reports.today.index', ['channel' => 'web']) }}"
                    class="px-4 py-2 text-sm font-semibold rounded-full {{ $channel === 'web' ? 'bg-gray-800 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}"
                >{{ __('Web') }}</a>
            </div>
        </div>

        {{-- Daily financial summary --}}
        <div class="bg-white shadow rounded-lg p-4">
            <p class="text-xs text-gray-500">{{ __('Total sales') }} ({{ strtoupper($channel) }})</p>
            <p class="text-2xl font-bold text-gray-800">GH₵{{ number_format($summary['total_sales'] / 100, 2) }}</p>
            <p class="text-xs text-gray-500 mt-1">{{ __(':count orders today', ['count' => $summary['orders_count']]) }}</p>

            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 pt-3 mt-3 border-t border-gray-100">
                @forelse ($summary['by_payment_method'] as $method => $amount)
                    <div>
                        <p class="text-xs text-gray-500 capitalize">{{ $method }}</p>
                        <p class="text-sm font-semibold text-gray-800">GH₵{{ number_format($amount / 100, 2) }}</p>
                    </div>
                @empty
                    <p class="text-sm text-gray-500">{{ __('No sales yet today.') }}</p>
                @endforelse
            </div>
        </div>

        {{-- Shifts today — total_sales is the staff-entered figure (required
             at close for staff, optional for manager/owner), system_sales
             is the snapshot of what the system had recorded at that exact
             moment (see ShiftController::end()). Anything entered above it
             shows here as "Extra" rather than getting silently dropped. --}}
        @if ($shifts->isNotEmpty())
            <section class="space-y-2">
                <h3 class="font-semibold text-gray-800 uppercase text-sm tracking-wide">{{ __('Shifts today') }}</h3>
                <div class="bg-white shadow rounded-lg overflow-hidden overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 text-left text-gray-500">
                            <tr>
                                <th class="px-4 py-2">{{ __('Staff') }}</th>
                                <th class="px-4 py-2">{{ __('Started') }}</th>
                                <th class="px-4 py-2">{{ __('Ended') }}</th>
                                <th class="px-4 py-2 text-right">{{ __('Total sales') }}</th>
                                <th class="px-4 py-2 text-right">{{ __('System sales') }}</th>
                                <th class="px-4 py-2 text-right">{{ __('Extra') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($shifts as $shift)
                                @php
                                    $extra = ($shift->total_sales !== null && $shift->system_sales !== null)
                                        ? max(0, $shift->total_sales - $shift->system_sales)
                                        : null;
                                @endphp
                                <tr>
                                    <td class="px-4 py-2 text-gray-800">{{ $shift->user->name }}</td>
                                    <td class="px-4 py-2 text-gray-500">{{ $shift->started_at->timezone('Africa/Accra')->format('H:i') }}</td>
                                    <td class="px-4 py-2 text-gray-500">
                                        {{ $shift->ended_at?->timezone('Africa/Accra')->format('H:i') ?? __('Active') }}
                                    </td>
                                    <td class="px-4 py-2 text-right text-gray-800">
                                        {{ $shift->total_sales !== null ? 'GH₵'.number_format($shift->total_sales / 100, 2) : '—' }}
                                    </td>
                                    <td class="px-4 py-2 text-right text-gray-500">
                                        {{ $shift->system_sales !== null ? 'GH₵'.number_format($shift->system_sales / 100, 2) : '—' }}
                                    </td>
                                    <td class="px-4 py-2 text-right font-medium {{ $extra ? 'text-green-700' : 'text-gray-400' }}">
                                        {{ $extra ? 'GH₵'.number_format($extra / 100, 2) : '—' }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @endif

        {{-- Category sections --}}
        @forelse ($summary['categories'] as $group)
            <section class="space-y-2">
                <div class="flex items-center justify-between">
                    <h3 class="font-semibold text-gray-800 uppercase text-sm tracking-wide">{{ $group['category'] }}</h3>
                    <span class="text-sm text-gray-500">GH₵{{ number_format($group['subtotal'] / 100, 2) }}</span>
                </div>
                <div class="bg-white shadow rounded-lg overflow-hidden overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 text-left text-gray-500">
                            <tr>
                                <th class="px-4 py-2">{{ __('Item') }}</th>
                                <th class="px-4 py-2 text-right">{{ __('Qty') }}</th>
                                <th class="px-4 py-2 text-right">{{ __('Unit') }}</th>
                                <th class="px-4 py-2 text-right">{{ __('Total') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($group['items'] as $line)
                                <tr>
                                    <td class="px-4 py-2 text-gray-800">{{ $line['name'] }}</td>
                                    <td class="px-4 py-2 text-right text-gray-500">{{ $line['qty'] }}</td>
                                    <td class="px-4 py-2 text-right text-gray-500">GH₵{{ number_format($line['unit'] / 100, 2) }}</td>
                                    <td class="px-4 py-2 text-right text-gray-800 font-medium">GH₵{{ number_format($line['total'] / 100, 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @empty
            <p class="text-sm text-gray-500">{{ __('No sales recorded yet today.') }}</p>
        @endforelse

        {{-- Modifiers --}}
        @if ($summary['modifiers']['items']->isNotEmpty())
            <section class="space-y-2">
                <div class="flex items-center justify-between">
                    <h3 class="font-semibold text-gray-800 uppercase text-sm tracking-wide">{{ __('Modifiers') }}</h3>
                    <span class="text-sm text-gray-500">GH₵{{ number_format($summary['modifiers']['subtotal'] / 100, 2) }}</span>
                </div>
                <div class="bg-white shadow rounded-lg overflow-hidden overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 text-left text-gray-500">
                            <tr>
                                <th class="px-4 py-2">{{ __('Item') }}</th>
                                <th class="px-4 py-2 text-right">{{ __('Qty') }}</th>
                                <th class="px-4 py-2 text-right">{{ __('Unit') }}</th>
                                <th class="px-4 py-2 text-right">{{ __('Total') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($summary['modifiers']['items'] as $line)
                                <tr>
                                    <td class="px-4 py-2 text-gray-800">{{ $line['name'] }}</td>
                                    <td class="px-4 py-2 text-right text-gray-500">{{ $line['qty'] }}</td>
                                    <td class="px-4 py-2 text-right text-gray-500">GH₵{{ number_format($line['unit'] / 100, 2) }}</td>
                                    <td class="px-4 py-2 text-right text-gray-800 font-medium">GH₵{{ number_format($line['total'] / 100, 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @endif
    </div>
</x-app-layout>
