<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Refunds') }}</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-6xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @if (session('status'))
                <div class="rounded-md bg-green-50 text-green-700 text-sm px-4 py-2">{{ session('status') }}</div>
            @endif
            @error('refund')
                <div class="rounded-md bg-red-50 text-red-700 text-sm px-4 py-2">{{ $message }}</div>
            @enderror

            <div class="bg-white shadow rounded-lg p-4">
                <form method="GET" action="{{ route('dashboard.refunds.index') }}" class="flex flex-wrap items-end gap-3">
                    <div>
                        <label for="channel" class="block text-xs font-medium text-gray-500 mb-1">{{ __('Channel') }}</label>
                        <select id="channel" name="channel" class="rounded-md border-gray-300 text-sm">
                            <option value="">{{ __('Web + POS') }}</option>
                            <option value="web" @selected($channel === 'web')>{{ __('Web') }}</option>
                            <option value="pos" @selected($channel === 'pos')>{{ __('POS') }}</option>
                        </select>
                    </div>

                    <div>
                        <label for="status" class="block text-xs font-medium text-gray-500 mb-1">{{ __('Status') }}</label>
                        <select id="status" name="status" class="rounded-md border-gray-300 text-sm">
                            <option value="">{{ __('Any status') }}</option>
                            @foreach ($statuses as $value)
                                <option value="{{ $value }}" @selected($status === $value)>{{ ucfirst($value) }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="range" class="block text-xs font-medium text-gray-500 mb-1">{{ __('Date range') }}</label>
                        <select id="range" name="range" class="rounded-md border-gray-300 text-sm">
                            <option value="today" @selected($rangeKey === 'today')>{{ __('Today') }}</option>
                            <option value="7" @selected($rangeKey === '7')>{{ __('Last 7 days') }}</option>
                            <option value="30" @selected($rangeKey === '30')>{{ __('Last 30 days') }}</option>
                        </select>
                    </div>

                    <button type="submit" class="px-4 py-2 bg-gray-800 text-white text-sm font-semibold rounded-md hover:bg-gray-900">
                        {{ __('Apply filters') }}
                    </button>
                </form>
            </div>

            <div class="bg-white shadow rounded-lg overflow-hidden overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-left text-gray-500">
                        <tr>
                            <th class="px-4 py-2">{{ __('Order') }}</th>
                            <th class="px-4 py-2">{{ __('Branch') }}</th>
                            <th class="px-4 py-2">{{ __('Channel') }}</th>
                            <th class="px-4 py-2 text-right">{{ __('Amount') }}</th>
                            <th class="px-4 py-2">{{ __('Reason') }}</th>
                            <th class="px-4 py-2">{{ __('Requested by') }}</th>
                            <th class="px-4 py-2">{{ __('Status') }}</th>
                            <th class="px-4 py-2">{{ __('Requested') }}</th>
                            <th class="px-4 py-2"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($refunds as $refund)
                            @php
                                $badgeClass = match ($refund->status) {
                                    'completed' => 'bg-green-50 text-green-700',
                                    'denied' => 'bg-red-50 text-red-700',
                                    'approved' => 'bg-amber-50 text-amber-700',
                                    default => 'bg-gray-100 text-gray-600',
                                };
                            @endphp
                            <tr>
                                <td class="px-4 py-2 font-medium">
                                    <a href="{{ route('dashboard.orders.show', $refund->order) }}" class="text-indigo-600 hover:underline">
                                        {{ $refund->order->reference }}
                                    </a>
                                </td>
                                <td class="px-4 py-2 text-gray-500">{{ $refund->branch->name }}</td>
                                <td class="px-4 py-2 text-gray-500 uppercase">{{ $refund->order->channel }}</td>
                                <td class="px-4 py-2 text-right text-gray-800">GH₵{{ number_format($refund->amount / 100, 2) }}</td>
                                <td class="px-4 py-2 text-gray-500 max-w-xs truncate" title="{{ $refund->reason }}">{{ $refund->reason }}</td>
                                <td class="px-4 py-2 text-gray-500">{{ $refund->requestedBy->name }}</td>
                                <td class="px-4 py-2">
                                    <span class="text-xs font-medium px-2 py-1 rounded-md capitalize {{ $badgeClass }}">{{ $refund->status }}</span>
                                </td>
                                <td class="px-4 py-2 text-gray-500">{{ $refund->created_at->timezone('Africa/Accra')->format('d M Y, H:i') }}</td>
                                <td class="px-4 py-2 text-right">
                                    @if ($refund->status === 'pending')
                                        <div class="flex items-center justify-end gap-2">
                                            @can('approve', $refund)
                                                <form method="POST" action="{{ route('dashboard.refunds.approve', $refund) }}">
                                                    @csrf
                                                    <button type="submit" class="text-green-700 hover:underline">{{ __('Approve') }}</button>
                                                </form>
                                            @endcan
                                            @can('deny', $refund)
                                                <form method="POST" action="{{ route('dashboard.refunds.deny', $refund) }}"
                                                    onsubmit="return confirm({{ Js::from(__('Deny this refund request?')) }})">
                                                    @csrf
                                                    <button type="submit" class="text-red-600 hover:underline">{{ __('Deny') }}</button>
                                                </form>
                                            @endcan
                                        </div>
                                    @elseif ($refund->status === 'approved')
                                        @can('complete', $refund)
                                            <form method="POST" action="{{ route('dashboard.refunds.complete', $refund) }}">
                                                @csrf
                                                <button type="submit" class="text-gray-800 font-semibold hover:underline">{{ __('Complete') }}</button>
                                            </form>
                                        @endcan
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="px-4 py-6 text-center text-gray-500">{{ __('No refunds match these filters.') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{ $refunds->links() }}
        </div>
    </div>
</x-app-layout>
