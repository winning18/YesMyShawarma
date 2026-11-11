<x-rider-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Past deliveries') }}</h2>
    </x-slot>

    @php
        $money = fn (int $pesewas) => 'GH₵'.number_format($pesewas / 100, 2);
    @endphp

    <div class="py-12">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 space-y-4">
            @if (session('status'))
                <div class="rounded-md bg-green-50 text-green-700 text-sm px-4 py-2">{{ session('status') }}</div>
            @endif
            <div class="bg-white shadow-sm rounded-lg p-6 space-y-4">
                <h3 class="font-semibold text-gray-900">{{ __('Delivery fees') }}</h3>

                <div class="grid grid-cols-2 gap-4">
                    @foreach ([__('Today') => $today, __('This week') => $thisWeek] as $label => $totals)
                        <div>
                            <p class="text-xs text-gray-500">{{ $label }}</p>
                            <p class="text-xl font-semibold text-gray-900">{{ $money($totals['to_collect']) }} <span class="text-sm font-normal text-gray-500">{{ __('to hand in') }}</span></p>
                            <p class="text-xs text-gray-500">
                                {{ trans_choice(':count delivery|:count deliveries', $totals['count'], ['count' => $totals['count']]) }} ·
                                {{ __(':cash cash collected · :handed handed in', ['cash' => $money($totals['cash']), 'handed' => $money($totals['collected'])]) }}
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
                        {{ __(':count deliveries', ['count' => $range['count']]) }} ·
                        {{ __(':cash cash collected', ['cash' => $money($range['cash'])]) }} ·
                        <span class="font-semibold">{{ __(':amount to hand in', ['amount' => $money($range['to_collect'])]) }}</span> ·
                        {{ __(':amount handed in', ['amount' => $money($range['collected'])]) }}
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
                            <span class="text-gray-500">· {{ $split['prepaid'] === 0 ? __('cash from customer') : ($split['cash'] === 0 ? __('prepaid, nothing to hand in') : __(':amount in cash, the rest prepaid', ['amount' => $money($split['cash'])])) }}</span>
                        </p>
                    @endif
                    <p class="text-xs text-gray-400 mt-2">{{ ($order->delivered_at ?? $order->updated_at)->timezone('Africa/Accra')->format('d M Y, H:i') }}</p>

                    @php $card = $order->refund_card; $refund = $card['refund']; @endphp
                    <x-input-error class="mt-2" :messages="$errors->get('refund_'.$order->id)" />
                    @if ($refund)
                        <div class="mt-3 rounded-md px-3 py-2 text-sm {{ $refund->status === 'denied' ? 'bg-red-50 text-red-700' : ($refund->status === 'completed' ? 'bg-green-50 text-green-700' : 'bg-amber-50 text-amber-800') }}">
                            @switch($refund->status)
                                @case('pending')
                                    {{ __('Refund of :amount requested, waiting for a manager to approve.', ['amount' => $money($refund->amount)]) }}
                                    @break
                                @case('approved')
                                    @if ($card['can_give'])
                                        <p class="font-semibold">{{ __('Refund approved: give :amount back to the customer.', ['amount' => $money($refund->amount)]) }}</p>
                                        <form method="POST" action="{{ route('rider.refunds.complete', $refund->id) }}" class="mt-2"
                                            onsubmit="return confirm(@js(__('Confirm you have given the customer :amount?', ['amount' => $money($refund->amount)])))">
                                            @csrf
                                            <button type="submit" class="px-3 py-1.5 bg-gray-800 text-white text-xs font-semibold rounded-md">{{ __('Refund given') }}</button>
                                        </form>
                                    @else
                                        {{ __('Refund of :amount approved. The branch will refund the customer.', ['amount' => $money($refund->amount)]) }}
                                    @endif
                                    @break
                                @case('completed')
                                    {{ __('Refunded :amount.', ['amount' => $money($refund->amount)]) }}
                                    @break
                                @case('denied')
                                    {{ __('Refund not approved.') }}@if ($refund->review_note) {{ $refund->review_note }}@endif
                                    @break
                            @endswitch
                        </div>
                    @endif

                    @if ($card['can_request'] && $card['remaining'] > 0)
                        <details class="mt-3">
                            <summary class="text-sm text-red-700 font-semibold cursor-pointer">{{ __('Request refund') }}</summary>
                            <form method="POST" action="{{ route('rider.refunds.store', $order->id) }}" class="mt-2 space-y-2">
                                @csrf
                                <p class="text-xs text-gray-500">{{ __('Full refund of :amount (food and delivery fee). A manager approves it first; they can lower the amount.', ['amount' => $money($card['remaining'])]) }}</p>
                                <textarea name="reason" rows="2" required maxlength="255" class="w-full rounded-md border-gray-300 text-sm" placeholder="{{ __('What went wrong? e.g. wrong order, missing items') }}"></textarea>
                                <button type="submit" class="px-3 py-1.5 bg-red-700 text-white text-xs font-semibold rounded-md">{{ __('Send refund request') }}</button>
                            </form>
                        </details>
                    @endif
                </div>
            @empty
                <p class="text-sm text-gray-500">{{ __("You haven't completed any deliveries yet.") }}</p>
            @endforelse

            {{ $orders->links() }}
        </div>
    </div>
</x-rider-layout>
