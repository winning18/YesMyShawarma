<x-customer-layout title="My orders · {{ config('app.name') }}">
    <div class="max-w-2xl mx-auto">
        <h1 class="text-2xl font-bold mb-4">{{ __('My account') }}</h1>
        @include('customer.account._nav', ['active' => 'orders'])

        @if ($orders->isEmpty())
            <p class="text-brand-gray-500">{{ __("You haven't placed any orders yet.") }}</p>
            <a href="{{ route('menu.index') }}" class="inline-block mt-4 text-sm font-semibold text-brand-red hover:text-brand-red-dark">{{ __('Browse the menu →') }}</a>
        @else
            <div class="space-y-3">
                @foreach ($orders as $order)
                    @php $paidItems = $order->items->where('is_free', false); @endphp
                    <div class="border border-brand-gray-100 rounded-lg p-4">
                        <div class="flex flex-wrap justify-between gap-2">
                            <div>
                                <p class="font-semibold">{{ $order->reference }}</p>
                                <p class="text-sm text-brand-gray-500">{{ $order->placed_at->timezone('Africa/Accra')->format('d M Y, g:ia') }} · {{ $order->branch?->name }}</p>
                            </div>
                            <div class="text-right">
                                <p class="font-semibold">GH₵{{ number_format($order->total / 100, 2) }}</p>
                                <p class="text-sm text-brand-gray-500">{{ ucfirst(str_replace('_', ' ', $order->status)) }}</p>
                            </div>
                        </div>
                        <p class="text-sm text-brand-gray-700 mt-2">
                            {{ $paidItems->map(fn ($item) => $item->quantity.'× '.$item->name_snapshot)->join(', ') }}
                        </p>
                        <div class="flex flex-wrap items-center gap-4 mt-3">
                            <form method="POST" action="{{ route('customer.account.reorder', $order) }}">
                                @csrf
                                <button type="submit" class="px-4 py-2 bg-brand-yellow text-brand-black text-sm font-semibold rounded-md hover:bg-brand-yellow-dark">{{ __('Order again') }}</button>
                            </form>
                            <a href="{{ route('tracking.show', $order) }}" class="text-sm underline text-brand-gray-500">{{ __('Track / details') }}</a>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="mt-6">{{ $orders->links() }}</div>
        @endif
    </div>
</x-customer-layout>
