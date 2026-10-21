<x-rider-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Past deliveries') }}</h2>
    </x-slot>

    @php
        $money = fn (int $pesewas) => 'GH₵'.number_format($pesewas / 100, 2);
    @endphp

    <div class="py-12">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 space-y-4">
            <div class="bg-white shadow-sm rounded-lg p-6 space-y-4">
                <h3 class="font-semibold text-gray-900">{{ __('Delivery fees') }}</h3>

                <div class="grid grid-cols-2 gap-4">
                    @foreach ([__('Today') => $today, __('This week') => $thisWeek] as $label => $totals)
                        <div>
                            <p class="text-xs text-gray-500">{{ $label }}</p>
                            <p class="text-xl font-semibold text-gray-900">{{ $money($totals['total']) }}</p>
                            <p class="text-xs text-gray-500">
                                {{ trans_choice(':count delivery|:count deliveries', $totals['count'], ['count' => $totals['count']]) }} ·
                                {{ __(':amount cash', ['amount' => $money($totals['cash'])]) }}
                            </p>
                        </div>
                    @endforeach
                </div>

                <form method="GET" action="{{ route('rider.history') }}" class="border-t border-gray-100 pt-4 space-y-3">
                    <div class="flex flex-wrap items-end gap-3">
                        <div>
                            <label for="from" class="block text-xs text-gray-500 mb-1">{{ __('From') }}</label>
                            <input type="date" id="from" name="from" value="{{ $from->toDateString() }}" class="rounded-md border-gray-300 text-sm">
                        </div>
                        <div>
                            <label for="to" class="block text-xs text-gray-500 mb-1">{{ __('To') }}</label>
                            <input type="date" id="to" name="to" value="{{ $to->toDateString() }}" class="rounded-md border-gray-300 text-sm">
                        </div>
                        <button type="submit" class="px-4 py-2 bg-gray-800 text-white text-sm font-semibold rounded-md">{{ __('Show') }}</button>
                    </div>
                    <p class="text-sm text-gray-700">
                        {{ $from->format('d M') }} – {{ $to->format('d M Y') }}:
                        <span class="font-semibold">{{ $money($range['total']) }}</span>
                        {{ __('from :count deliveries', ['count' => $range['count']]) }} ·
                        {{ __(':cash collected in cash, :prepaid paid before delivery', ['cash' => $money($range['cash']), 'prepaid' => $money($range['prepaid'])]) }}
                    </p>
                </form>
            </div>

            @forelse ($orders as $order)
                <div class="bg-white shadow-sm rounded-lg p-6">
                    <div class="flex justify-between items-start">
                        <div>
                            <p class="font-semibold text-gray-900">{{ $order->reference }}</p>
                            <p class="text-sm text-gray-500">
                                {{ $order->delivery_address_snapshot['area_name'] ?? __('No area on file') }}
                            </p>
                        </div>
                        <span
                            class="text-sm font-medium px-2 py-1 rounded-md {{ $order->status === 'delivered' ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-700' }}"
                        >{{ $order->status === 'delivered' ? __('Delivered') : __('Failed') }}</span>
                    </div>
                    @if ($split = $order->fee_split)
                        <p class="text-sm text-gray-700 mt-2">
                            {{ __('Delivery fee') }} <span class="font-semibold">{{ $money($split['fee']) }}</span>
                            <span class="text-gray-500">· {{ $split['prepaid'] === 0 ? __('collected in cash') : ($split['cash'] === 0 ? __('paid before delivery') : __(':amount in cash', ['amount' => $money($split['cash'])])) }}</span>
                        </p>
                    @endif
                    <p class="text-xs text-gray-400 mt-2">{{ ($order->delivered_at ?? $order->updated_at)->timezone('Africa/Accra')->format('d M Y, H:i') }}</p>
                </div>
            @empty
                <p class="text-sm text-gray-500">{{ __("You haven't completed any deliveries yet.") }}</p>
            @endforelse

            {{ $orders->links() }}
        </div>
    </div>
</x-rider-layout>
