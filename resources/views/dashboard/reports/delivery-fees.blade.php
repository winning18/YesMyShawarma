<x-app-layout>
    <x-slot name="header">
        @include('dashboard.reports._tabs', ['active' => 'delivery-fees'])
    </x-slot>

    <div class="max-w-5xl mx-auto py-8 px-4 space-y-6">
        @php
            $branchParam = $branchFilterId ? ['branch' => $branchFilterId] : [];
            $rangeParams = ['from' => $from->toDateString(), 'to' => $to->toDateString()];
            $now = now('Africa/Accra');
            $rangePresets = [
                [__('This week'), $now->clone()->startOfWeek(), $now->clone()->endOfWeek()],
                [__('Last week'), $now->clone()->subWeek()->startOfWeek(), $now->clone()->subWeek()->endOfWeek()],
                [__('This month'), $now->clone()->startOfMonth(), $now->clone()->endOfMonth()],
                [__('Last month'), $now->clone()->subMonthNoOverflow()->startOfMonth(), $now->clone()->subMonthNoOverflow()->endOfMonth()],
            ];
            $dateRangePresetLinks = collect($rangePresets)->map(fn ($preset) => [
                'label' => $preset[0],
                'url' => request()->fullUrlWithQuery(['from' => $preset[1]->toDateString(), 'to' => $preset[2]->toDateString(), 'page' => null]),
                'active' => $from->isSameDay($preset[1]) && $to->isSameDay($preset[2]),
            ])->all();
            $money = fn (int $pesewas) => 'GH₵'.number_format($pesewas / 100, 2);
            $selectedRider = $riderId !== null ? $riders->firstWhere('rider_id', $riderId) : null;
        @endphp

        <div>
            <h3 class="font-semibold text-lg text-gray-800">{{ __('Delivery fees') }}</h3>
            <p class="text-sm text-gray-500">{{ __('Delivery fees on every delivered order, by rider. Each rider sees the same totals in their app under Past deliveries.') }}</p>
        </div>

        <div class="bg-white shadow rounded-lg p-4">
            <form method="GET" action="{{ route('dashboard.reports.delivery-fees.index') }}" class="space-y-3">
                @if ($riderId !== null)
                    <input type="hidden" name="rider" value="{{ $riderId }}">
                @endif
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('Dates') }}</label>
                        <x-date-range-picker
                            :from="$from->toDateString()"
                            :to="$to->toDateString()"
                            :label="$from->format('d/m/Y').' - '.$to->format('d/m/Y')"
                            :preset-links="$dateRangePresetLinks"
                        />
                    </div>

                    @if ($crossBranch)
                        <div>
                            <label for="branch" class="block text-xs font-medium text-gray-500 mb-1">{{ __('Branch') }}</label>
                            <select id="branch" name="branch" class="w-full rounded-md border-gray-300 text-sm">
                                <option value="">{{ __('All branches') }}</option>
                                @foreach ($branchOptions as $branch)
                                    <option value="{{ $branch->id }}" @selected($branchFilterId === $branch->id)>{{ $branch->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif
                </div>

                <div class="flex flex-wrap items-center gap-3">
                    <button type="submit" class="px-4 py-2 bg-gray-800 text-white text-sm font-semibold rounded-md hover:bg-gray-900">
                        {{ __('Apply filters') }}
                    </button>
                    <a
                        href="{{ route('dashboard.reports.delivery-fees.download', [...$branchParam, ...$rangeParams, ...($riderId !== null ? ['rider' => $riderId] : [])]) }}"
                        class="ml-auto px-4 py-2 bg-green-700 text-white text-sm font-semibold rounded-full hover:bg-green-800"
                    >{{ __('Download CSV') }}</a>
                </div>
            </form>
        </div>

        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="bg-white shadow rounded-lg p-4">
                <p class="text-xs text-gray-500">{{ __('Total delivery fees') }}</p>
                <p class="text-xl font-semibold text-gray-800">{{ $money($summary['total']) }}</p>
            </div>
            <div class="bg-white shadow rounded-lg p-4">
                <p class="text-xs text-gray-500">{{ __('Deliveries') }}</p>
                <p class="text-xl font-semibold text-gray-800">{{ $summary['count'] }}</p>
            </div>
            <div class="bg-white shadow rounded-lg p-4">
                <p class="text-xs text-gray-500">{{ __('Cash collected by riders') }}</p>
                <p class="text-xl font-semibold text-gray-800">{{ $money($summary['cash']) }}</p>
            </div>
            <div class="bg-white shadow rounded-lg p-4">
                <p class="text-xs text-gray-500">{{ __('Paid before delivery') }}</p>
                <p class="text-xl font-semibold text-gray-800">{{ $money($summary['prepaid']) }}</p>
                <p class="text-xs text-gray-400">{{ __('Paystack or MoMo, not via the rider') }}</p>
            </div>
        </div>

        <div class="bg-white shadow rounded-lg overflow-hidden overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-left text-gray-500">
                    <tr>
                        <th class="px-4 py-2">{{ __('Rider') }}</th>
                        <th class="px-4 py-2 text-right">{{ __('Deliveries') }}</th>
                        <th class="px-4 py-2 text-right">{{ __('Cash collected') }}</th>
                        <th class="px-4 py-2 text-right">{{ __('Paid before delivery') }}</th>
                        <th class="px-4 py-2 text-right">{{ __('Total fees') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($riders as $rider)
                        <tr class="{{ $riderId === $rider['rider_id'] ? 'bg-green-50' : '' }}">
                            <td class="px-4 py-2">
                                <a href="{{ route('dashboard.reports.delivery-fees.index', [...$branchParam, ...$rangeParams, 'rider' => $rider['rider_id']]) }}" class="text-gray-800 font-medium hover:underline">{{ $rider['rider'] }}</a>
                            </td>
                            <td class="px-4 py-2 text-right text-gray-500">{{ $rider['count'] }}</td>
                            <td class="px-4 py-2 text-right text-gray-800">{{ $money($rider['cash']) }}</td>
                            <td class="px-4 py-2 text-right text-gray-500">{{ $money($rider['prepaid']) }}</td>
                            <td class="px-4 py-2 text-right font-semibold text-gray-800">{{ $money($rider['total']) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-6 text-center text-gray-500">{{ __('No deliveries in these dates.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($summary['count'] > 0)
            <div class="flex flex-wrap items-baseline justify-between gap-2">
                <h3 class="font-semibold text-gray-800">
                    {{ $selectedRider ? __('Deliveries by :rider', ['rider' => $selectedRider['rider']]) : __('All deliveries') }}
                    @if ($riderSummary)
                        <span class="font-normal text-gray-500 text-sm">· {{ $riderSummary['count'] }} · {{ $money($riderSummary['total']) }}</span>
                    @endif
                </h3>
                @if ($riderId !== null)
                    <a href="{{ route('dashboard.reports.delivery-fees.index', [...$branchParam, ...$rangeParams]) }}" class="text-sm text-gray-500 hover:underline">{{ __('Show all riders') }}</a>
                @endif
            </div>

            <div class="bg-white shadow rounded-lg overflow-hidden overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-left text-gray-500">
                        <tr>
                            <th class="px-4 py-2">{{ __('Delivered') }}</th>
                            <th class="px-4 py-2">{{ __('Order') }}</th>
                            @if ($riderId === null)
                                <th class="px-4 py-2">{{ __('Rider') }}</th>
                            @endif
                            @if ($crossBranch && ! $branchFilterId)
                                <th class="px-4 py-2">{{ __('Branch') }}</th>
                            @endif
                            <th class="px-4 py-2">{{ __('Area') }}</th>
                            <th class="px-4 py-2">{{ __('Paid by') }}</th>
                            <th class="px-4 py-2 text-right">{{ __('Fee') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($orders as $order)
                            <tr>
                                <td class="px-4 py-2 text-gray-500 whitespace-nowrap">{{ $order['delivered_at']->format('d/m/Y H:i') }}</td>
                                <td class="px-4 py-2 text-gray-800 whitespace-nowrap">{{ $order['reference'] }}</td>
                                @if ($riderId === null)
                                    <td class="px-4 py-2 text-gray-500">{{ $order['rider'] }}</td>
                                @endif
                                @if ($crossBranch && ! $branchFilterId)
                                    <td class="px-4 py-2 text-gray-500">{{ $order['branch'] }}</td>
                                @endif
                                <td class="px-4 py-2 text-gray-500">{{ $order['area'] ?? '—' }}</td>
                                <td class="px-4 py-2 text-gray-500">
                                    {{ $order['prepaid'] === 0 ? __('Cash to rider') : ($order['cash'] === 0 ? __('Paid before delivery') : __('Part cash')) }}
                                </td>
                                <td class="px-4 py-2 text-right whitespace-nowrap">
                                    <span class="text-gray-800">{{ $money($order['fee']) }}</span>
                                    @if ($order['fee_note'])
                                        <span class="block text-xs text-amber-700">{{ $order['fee_note'] }}</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-4 py-6 text-center text-gray-500">{{ __('No deliveries for this rider in these dates.') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{ $orders->links() }}
        @endif
    </div>
</x-app-layout>
