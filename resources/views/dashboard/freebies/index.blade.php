<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Freebies & rewards') }}</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            @php $money = fn (int $pesewas) => 'GH₵'.number_format($pesewas / 100, 2); @endphp

            @if (session('status'))
                <div class="rounded-md bg-green-50 text-green-700 text-sm px-4 py-2">{{ session('status') }}</div>
            @endif
            @if ($errors->any())
                <div class="rounded-md bg-red-50 text-red-700 text-sm px-4 py-2">{{ $errors->first() }}</div>
            @endif

            <p class="text-sm text-gray-500">{{ __('Freebies are food given free to customers or staff from the POS. They\'re never counted as sales; their value is the menu price. WhatsApp group orders get a reward off their food and still count as sales.') }}</p>

            <form method="GET" action="{{ route('dashboard.freebies.index') }}" class="bg-white shadow rounded-lg p-4 flex flex-wrap items-end gap-3">
                <div>
                    <label for="from" class="block text-xs font-medium text-gray-500 mb-1">{{ __('From') }}</label>
                    <input id="from" type="date" name="from" value="{{ $from->toDateString() }}" class="rounded-md border-gray-300 text-sm">
                </div>
                <div>
                    <label for="to" class="block text-xs font-medium text-gray-500 mb-1">{{ __('To') }}</label>
                    <input id="to" type="date" name="to" value="{{ $to->toDateString() }}" class="rounded-md border-gray-300 text-sm">
                </div>
                <div>
                    <label for="status" class="block text-xs font-medium text-gray-500 mb-1">{{ __('Status') }}</label>
                    <select id="status" name="status" class="rounded-md border-gray-300 text-sm">
                        <option value="">{{ __('Any status') }}</option>
                        @foreach (\App\Models\ComplimentaryOrder::STATUSES as $value)
                            <option value="{{ $value }}" @selected($status === $value)>{{ __(ucfirst($value)) }}</option>
                        @endforeach
                    </select>
                </div>
                <button type="submit" class="px-4 py-2 bg-gray-800 text-white text-sm font-semibold rounded-md hover:bg-gray-900">{{ __('Apply') }}</button>
            </form>

            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="bg-white shadow rounded-lg p-4">
                    <p class="text-xs text-gray-500">{{ __('Freebies given') }}</p>
                    <p class="text-xl font-semibold text-gray-800">{{ $money($summary['value']) }}</p>
                    <p class="text-xs text-gray-400">{{ trans_choice(':count freebie|:count freebies', $summary['count'], ['count' => $summary['count']]) }}</p>
                </div>
                <div class="bg-white shadow rounded-lg p-4">
                    <p class="text-xs text-gray-500">{{ __('Waiting for approval') }}</p>
                    <p class="text-xl font-semibold {{ $summary['pending']['count'] ? 'text-amber-700' : 'text-gray-800' }}">{{ $summary['pending']['count'] }}</p>
                    <p class="text-xs text-gray-400">{{ $money($summary['pending']['value']) }}</p>
                </div>
                <div class="bg-white shadow rounded-lg p-4">
                    <p class="text-xs text-gray-500">{{ __('Rejected (follow up)') }}</p>
                    <p class="text-xl font-semibold {{ $summary['rejected']['count'] ? 'text-red-600' : 'text-gray-800' }}">{{ $money($summary['rejected']['value']) }}</p>
                    <p class="text-xs text-gray-400">{{ trans_choice(':count freebie|:count freebies', $summary['rejected']['count'], ['count' => $summary['rejected']['count']]) }}</p>
                </div>
                <div class="bg-white shadow rounded-lg p-4">
                    <p class="text-xs text-gray-500">{{ __('WhatsApp group rewards') }}</p>
                    <p class="text-xl font-semibold text-gray-800">{{ $money($whatsapp['rewards']) }}</p>
                    <p class="text-xs text-gray-400">{{ trans_choice(':count WhatsApp order|:count WhatsApp orders', $whatsapp['count'], ['count' => $whatsapp['count']]) }} · {{ __(':amount sales', ['amount' => $money($whatsapp['sales'])]) }}</p>
                </div>
            </div>

            @if ($summary['count'] > 0)
                <div class="grid md:grid-cols-3 gap-4">
                    @foreach (['by_reason' => __('By reason'), 'by_giver' => __('By who gave it'), 'by_recipient' => __('By who got it')] as $key => $title)
                        <div class="bg-white shadow rounded-lg p-4">
                            <h3 class="font-semibold text-gray-800 text-sm mb-2">{{ $title }}</h3>
                            <ul class="text-sm space-y-1">
                                @foreach (array_slice($summary[$key], 0, 8) as $row)
                                    <li class="flex justify-between gap-2"><span class="truncate">{{ $row['label'] }} <span class="text-gray-400">({{ $row['count'] }})</span></span><span class="whitespace-nowrap">{{ $money($row['value']) }}</span></li>
                                @endforeach
                            </ul>
                        </div>
                    @endforeach
                </div>
            @endif

            <div class="bg-white shadow rounded-lg overflow-hidden overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-left text-gray-500">
                        <tr>
                            <th class="px-4 py-2">{{ __('When') }}</th>
                            <th class="px-4 py-2">{{ __('Order') }}</th>
                            <th class="px-4 py-2">{{ __('For') }}</th>
                            <th class="px-4 py-2">{{ __('Reason') }}</th>
                            <th class="px-4 py-2">{{ __('Given by') }}</th>
                            <th class="px-4 py-2 text-right">{{ __('Value') }}</th>
                            <th class="px-4 py-2">{{ __('Status') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($freebies as $freebie)
                            @php
                                $order = $freebie->order;
                                $badge = match ($freebie->status) {
                                    'approved' => 'bg-green-50 text-green-700',
                                    'rejected' => 'bg-red-50 text-red-700',
                                    default => 'bg-amber-50 text-amber-700',
                                };
                            @endphp
                            <tr x-data="{ rejecting: false }">
                                <td class="px-4 py-2 text-gray-500 whitespace-nowrap">
                                    {{ $order->placed_at->timezone('Africa/Accra')->format('d/m H:i') }}
                                    <span class="block text-xs text-gray-400">{{ $order->branch?->name }}</span>
                                </td>
                                <td class="px-4 py-2">
                                    <a href="{{ route('dashboard.orders.show', $order) }}" class="text-indigo-600 hover:underline whitespace-nowrap">{{ $order->reference }}</a>
                                    <span class="block text-xs text-gray-500">{{ $order->items->map(fn ($item) => $item->quantity.'× '.$item->name_snapshot)->join(', ') }}</span>
                                </td>
                                <td class="px-4 py-2">{{ $freebie->recipientLabel() }} <span class="block text-xs text-gray-400">{{ $freebie->recipient_type === 'staff' ? __('Staff') : __('Customer') }}</span></td>
                                <td class="px-4 py-2 text-gray-700">{{ $freebie->reason }}</td>
                                <td class="px-4 py-2 text-gray-700">{{ $freebie->recordedBy?->name }}</td>
                                <td class="px-4 py-2 text-right whitespace-nowrap">{{ $money($order->subtotal) }}</td>
                                <td class="px-4 py-2">
                                    <span class="text-xs font-medium px-2 py-1 rounded-md {{ $badge }}">{{ __(ucfirst($freebie->status)) }}</span>
                                    @if ($freebie->status !== 'pending' && $freebie->reviewedBy)
                                        <span class="block text-xs text-gray-400 mt-1">{{ $freebie->reviewedBy->name }}@if ($freebie->review_note) · {{ $freebie->review_note }}@endif</span>
                                    @endif
                                    @can('review', $freebie)
                                        <div class="flex gap-3 mt-2 text-xs">
                                            <form method="POST" action="{{ route('dashboard.freebies.approve', $freebie) }}">
                                                @csrf
                                                <button type="submit" class="text-green-700 font-semibold hover:underline">{{ __('Approve') }}</button>
                                            </form>
                                            <button type="button" @click="rejecting = ! rejecting" class="text-red-600 hover:underline">{{ __('Reject') }}</button>
                                        </div>
                                        <form x-show="rejecting" x-cloak method="POST" action="{{ route('dashboard.freebies.reject', $freebie) }}" class="mt-2 flex gap-2">
                                            @csrf
                                            <input type="text" name="reason" required maxlength="1000" placeholder="{{ __('Why?') }}" class="rounded-md border-gray-300 text-xs w-36">
                                            <button type="submit" class="px-2 py-1 bg-red-600 text-white text-xs rounded-md">{{ __('Reject') }}</button>
                                        </form>
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="px-4 py-6 text-center text-gray-500">{{ __('No freebies in these dates.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
