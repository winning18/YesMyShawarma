<x-app-layout>
    <x-slot name="header">
        @include('dashboard.reports._tabs', ['active' => 'delivery-fees'])
    </x-slot>

    <div class="max-w-5xl mx-auto py-8 px-4 space-y-6">
        @php $money = fn (int $pesewas) => 'GH₵'.number_format($pesewas / 100, 2); @endphp

        <a href="{{ route('dashboard.reports.delivery-fees.index') }}" class="text-sm text-gray-500 hover:underline">&larr; {{ __('Delivery fees') }}</a>

        <div>
            <h3 class="font-semibold text-lg text-gray-800">{{ __('Collect delivery fees') }}</h3>
            <p class="text-sm text-gray-500">{{ __('Riders hand in the cash delivery fees they collect at the door, from :date. Paystack and MoMo fees are already with the business, so there\'s nothing to collect for those.', ['date' => $handInStartsOn->format('j F Y')]) }}</p>
        </div>

        @if (session('status'))
            <div class="rounded-md bg-green-50 text-green-700 text-sm px-4 py-2">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="rounded-md bg-red-50 text-red-700 text-sm px-4 py-2">{{ $errors->first() }}</div>
        @endif

        <div class="bg-white shadow rounded-lg p-4 space-y-4">
            <form method="GET" action="{{ route('dashboard.reports.delivery-fees.collections.index') }}" class="flex flex-wrap items-end gap-3">
                @if ($branches->count() > 1)
                    <div>
                        <label for="branch" class="block text-xs font-medium text-gray-500 mb-1">{{ __('Branch') }}</label>
                        <select id="branch" name="branch" class="rounded-md border-gray-300 text-sm" onchange="this.form.submit()">
                            <option value="">{{ __('Choose a branch') }}</option>
                            @foreach ($branches as $option)
                                <option value="{{ $option->id }}" @selected($branch?->id === $option->id)>{{ $option->name }}</option>
                            @endforeach
                        </select>
                    </div>
                @elseif ($branch)
                    <input type="hidden" name="branch" value="{{ $branch->id }}">
                @endif
                <div>
                    <label for="rider" class="block text-xs font-medium text-gray-500 mb-1">{{ __('Rider') }}</label>
                    <select id="rider" name="rider" class="rounded-md border-gray-300 text-sm" @disabled(! $branch)>
                        <option value="">{{ __('Choose a rider') }}</option>
                        @foreach ($riders as $option)
                            <option value="{{ $option->id }}" @selected($rider?->id === $option->id)>{{ $option->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="from" class="block text-xs font-medium text-gray-500 mb-1">{{ __('From') }}</label>
                    <input id="from" type="date" name="from" value="{{ $from->toDateString() }}" class="rounded-md border-gray-300 text-sm">
                </div>
                <div>
                    <label for="to" class="block text-xs font-medium text-gray-500 mb-1">{{ __('To') }}</label>
                    <input id="to" type="date" name="to" value="{{ $to->toDateString() }}" class="rounded-md border-gray-300 text-sm">
                </div>
                <button type="submit" class="px-4 py-2 bg-gray-800 text-white text-sm font-semibold rounded-md hover:bg-gray-900">{{ __('Show what\'s owed') }}</button>
            </form>

            @if ($owed)
                <div class="border-t border-gray-100 pt-4">
                    @if ($owed['count'] === 0)
                        <p class="text-sm text-gray-500">{{ __(':rider has no cash delivery fees left to hand in at :branch for these dates.', ['rider' => $rider->name, 'branch' => $branch->name]) }}</p>
                    @else
                        <p class="text-sm text-gray-700 mb-3">
                            {{ __(':rider owes', ['rider' => $rider->name]) }}
                            <strong class="text-lg">{{ $money($owed['expected']) }}</strong>
                            {{ trans_choice('from :count delivery|from :count deliveries', $owed['count'], ['count' => $owed['count']]) }}
                            ({{ $from->format('d M') }} – {{ $to->format('d M Y') }}, {{ $branch->name }}).
                        </p>

                        <div class="overflow-x-auto mb-4">
                            <table class="w-full text-sm">
                                <thead class="text-left text-xs text-gray-500 border-b border-gray-100">
                                    <tr>
                                        <th class="py-1 pr-2 font-medium">{{ __('Delivered') }}</th>
                                        <th class="py-1 pr-2 font-medium">{{ __('Order') }}</th>
                                        <th class="py-1 pr-2 font-medium">{{ __('Area') }}</th>
                                        <th class="py-1 font-medium text-right">{{ __('Cash fee') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-50">
                                    @foreach ($owed['rows'] as $row)
                                        <tr>
                                            <td class="py-1 pr-2 text-gray-500 whitespace-nowrap">{{ $row['delivered_at']->format('d/m H:i') }}</td>
                                            <td class="py-1 pr-2">{{ $row['reference'] }}</td>
                                            <td class="py-1 pr-2 text-gray-500">{{ $row['area'] ?? '—' }}</td>
                                            <td class="py-1 text-right whitespace-nowrap">{{ $money($row['cash']) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <form method="POST" action="{{ route('dashboard.reports.delivery-fees.collections.store') }}" class="flex flex-wrap items-end gap-3"
                            x-data="{ received: @js(number_format($owed['expected'] / 100, 2, '.', '')), expected: {{ $owed['expected'] }} }">
                            @csrf
                            <input type="hidden" name="branch" value="{{ $branch->id }}">
                            <input type="hidden" name="rider" value="{{ $rider->id }}">
                            <input type="hidden" name="from" value="{{ $from->toDateString() }}">
                            <input type="hidden" name="to" value="{{ $to->toDateString() }}">
                            <div>
                                <label for="received" class="block text-xs font-medium text-gray-500 mb-1">{{ __('Amount handed in (GH₵)') }} <span class="text-red-600">*</span></label>
                                <input id="received" type="number" name="received" required min="0" step="0.01" inputmode="decimal" x-model="received" class="w-36 rounded-md border-gray-300 text-sm">
                                <p class="text-xs mt-1" x-show="Math.round(parseFloat(received || 0) * 100) !== expected"
                                    :class="Math.round(parseFloat(received || 0) * 100) < expected ? 'text-red-600' : 'text-amber-700'"
                                    x-text="(Math.round(parseFloat(received || 0) * 100) < expected ? @js(__('Short by GH₵')) : @js(__('Over by GH₵'))) + (Math.abs(Math.round(parseFloat(received || 0) * 100) - expected) / 100).toFixed(2)"></p>
                            </div>
                            <div class="flex-1 min-w-[14rem]">
                                <label for="note" class="block text-xs font-medium text-gray-500 mb-1">{{ __('Note (optional)') }}</label>
                                <input id="note" type="text" name="note" maxlength="1000" class="w-full rounded-md border-gray-300 text-sm">
                            </div>
                            <button type="submit" class="px-4 py-2 bg-green-700 text-white text-sm font-semibold rounded-md hover:bg-green-800">{{ __('Mark as collected') }}</button>
                        </form>
                    @endif
                </div>
            @endif
        </div>

        <div>
            <h4 class="font-semibold text-gray-800 mb-2">{{ __('Collections') }}</h4>
            <div class="bg-white shadow rounded-lg overflow-hidden overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-left text-gray-500">
                        <tr>
                            <th class="px-4 py-2">{{ __('Recorded') }}</th>
                            <th class="px-4 py-2">{{ __('Rider') }}</th>
                            @if ($branches->count() > 1)
                                <th class="px-4 py-2">{{ __('Branch') }}</th>
                            @endif
                            <th class="px-4 py-2">{{ __('Deliveries in') }}</th>
                            <th class="px-4 py-2 text-right">{{ __('Expected') }}</th>
                            <th class="px-4 py-2 text-right">{{ __('Received') }}</th>
                            <th class="px-4 py-2"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($history as $collection)
                            @php $difference = $collection->difference(); @endphp
                            <tr class="{{ $collection->voided_at ? 'text-gray-400' : '' }}" x-data="{ voiding: false }">
                                <td class="px-4 py-2 whitespace-nowrap">
                                    {{ $collection->collected_at->timezone('Africa/Accra')->format('d/m/Y H:i') }}
                                    <span class="block text-xs text-gray-400">{{ __('by :name', ['name' => $collection->collectedBy?->name]) }}</span>
                                </td>
                                <td class="px-4 py-2">{{ $collection->rider?->name }}</td>
                                @if ($branches->count() > 1)
                                    <td class="px-4 py-2">{{ $collection->branch?->name }}</td>
                                @endif
                                <td class="px-4 py-2 whitespace-nowrap">
                                    {{ $collection->period_from->format('d M') }} – {{ $collection->period_to->format('d M Y') }}
                                    <span class="block text-xs text-gray-400">{{ trans_choice(':count delivery|:count deliveries', $collection->order_count, ['count' => $collection->order_count]) }}</span>
                                </td>
                                <td class="px-4 py-2 text-right whitespace-nowrap">{{ $money($collection->expected) }}</td>
                                <td class="px-4 py-2 text-right whitespace-nowrap">
                                    {{ $money($collection->received) }}
                                    @if ($difference !== 0 && ! $collection->voided_at)
                                        <span class="block text-xs {{ $difference < 0 ? 'text-red-600' : 'text-amber-700' }}">
                                            {{ $difference < 0 ? __('Short by :amount', ['amount' => $money(-$difference)]) : __('Over by :amount', ['amount' => $money($difference)]) }}
                                        </span>
                                    @endif
                                </td>
                                <td class="px-4 py-2 text-right text-xs">
                                    @if ($collection->voided_at)
                                        <span title="{{ $collection->void_reason }}">{{ __('Voided by :name', ['name' => $collection->voidedBy?->name]) }}</span>
                                    @else
                                        <button type="button" @click="voiding = ! voiding" class="text-red-600 hover:underline">{{ __('Void') }}</button>
                                        <form x-show="voiding" x-cloak method="POST" action="{{ route('dashboard.reports.delivery-fees.collections.void', $collection) }}" class="mt-2 flex gap-2 justify-end">
                                            @csrf
                                            <input type="text" name="reason" required maxlength="1000" placeholder="{{ __('Why?') }}" class="rounded-md border-gray-300 text-xs w-40">
                                            <button type="submit" class="px-2 py-1 bg-red-600 text-white rounded-md">{{ __('Void') }}</button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                            @if ($collection->note)
                                <tr><td colspan="7" class="px-4 pb-2 -mt-2 text-xs text-gray-500">{{ $collection->note }}</td></tr>
                            @endif
                        @empty
                            <tr><td colspan="7" class="px-4 py-6 text-center text-gray-500">{{ __('No collections recorded yet.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-4">{{ $history->links() }}</div>
        </div>
    </div>
</x-app-layout>
